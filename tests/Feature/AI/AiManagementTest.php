<?php

declare(strict_types=1);

use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\AI\Services\ProviderKeyStore;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;

/*
|------------------------------------------------------------------------------
| Templates
|------------------------------------------------------------------------------
*/

it('redirects an unauthenticated visitor from the template list', function (): void {
    $this->get(route('ai.templates.index'))->assertRedirect(route('login'));
});

it('forbids a member without ai.use from the template list', function (): void {
    $company = workspace();
    $user = memberWith([], $company)->refresh();

    actingAsMember($user, $company)->get(route('ai.templates.index'), inertiaHeaders())->assertForbidden();
});

it('lists only templates from the active workspace', function (): void {
    $other = workspace();
    AiPromptTemplate::factory()->create(['company_id' => $other->id, 'name' => 'Foreign template']);

    $company = workspace();
    AiPromptTemplate::factory()->create(['company_id' => $company->id, 'name' => 'Mine']);

    $user = memberWith(['ai.use'], $company)->refresh();

    $response = actingAsMember($user, $company)
        ->get(route('ai.templates.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'ai/templates/index');

    $names = collect($response->json('props.table.rows'))->pluck('name')->all();

    expect($names)->toContain('Mine')->and($names)->not->toContain('Foreign template');
});

it('creates a template', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use', 'ai.templates.manage'], $company)->refresh();

    actingAsMember($user, $company)->post(route('ai.templates.store'), [
        'name' => 'Release notes',
        'prompt' => 'Summarise {{changes}} for {{audience}}.',
        'variables' => [
            ['name' => 'changes', 'label' => 'Changes', 'type' => 'textarea', 'required' => true],
            ['name' => 'audience', 'label' => 'Audience', 'type' => 'text', 'required' => false],
        ],
        'is_shared' => true,
    ])->assertRedirect();

    $template = AiPromptTemplate::query()->where('name', 'Release notes')->firstOrFail();

    expect($template->company_id)->toBe($company->id)
        ->and($template->slug)->toBe('release-notes')
        ->and($template->placeholders())->toBe(['changes', 'audience']);
});

it('rejects a template without a prompt', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use', 'ai.templates.manage'], $company)->refresh();

    actingAsMember($user, $company)
        ->post(route('ai.templates.store'), ['name' => 'Empty'])
        ->assertSessionHasErrors('prompt');
});

it('forbids creating a template without the manage permission', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    actingAsMember($user, $company)
        ->post(route('ai.templates.store'), ['name' => 'Nope', 'prompt' => 'x'])
        ->assertForbidden();
});

it('leaves an omitted field unchanged on update', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use', 'ai.templates.manage'], $company)->refresh();

    $template = AiPromptTemplate::factory()->create([
        'company_id' => $company->id,
        'description' => 'Original description',
    ]);

    actingAsMember($user, $company)
        ->patch(route('ai.templates.update', $template->id), ['name' => 'Renamed'])
        ->assertRedirect();

    $template->refresh();

    expect($template->name)->toBe('Renamed')->and($template->description)->toBe('Original description');
});

it('cannot delete a template from another workspace', function (): void {
    $other = workspace();
    $foreign = AiPromptTemplate::factory()->create(['company_id' => $other->id]);

    $company = workspace();
    $user = memberWith(['ai.use', 'ai.templates.manage'], $company)->refresh();

    actingAsMember($user, $company)
        ->delete(route('ai.templates.destroy', $foreign->id))
        ->assertNotFound();
});

/*
|------------------------------------------------------------------------------
| History
|------------------------------------------------------------------------------
*/

it('forbids a member without ai.history.view', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    actingAsMember($user, $company)->get(route('ai.history.index'), inertiaHeaders())->assertForbidden();
});

it('lists only generations from the active workspace', function (): void {
    $other = workspace();
    AiGeneration::factory()->create(['company_id' => $other->id, 'input' => 'foreign prompt']);

    $company = workspace();
    AiGeneration::factory()->create(['company_id' => $company->id, 'input' => 'my prompt']);

    $user = memberWith(['ai.history.view'], $company)->refresh();

    $response = actingAsMember($user, $company)
        ->get(route('ai.history.index'), inertiaHeaders())
        ->assertOk();

    $inputs = collect($response->json('props.table.rows'))->pluck('input')->all();

    expect($inputs)->toContain('my prompt')->and($inputs)->not->toContain('foreign prompt');
});

/*
|------------------------------------------------------------------------------
| Credits
|------------------------------------------------------------------------------
*/

it('shows the balance and ledger', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    actingAsMember($user, $company)
        ->get(route('ai.credits.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'ai/credits')
        ->assertJsonPath('props.can.manage', false);
});

it('forbids adjusting credits without ai.credits.manage', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    actingAsMember($user, $company)
        ->post(route('ai.credits.adjust'), ['credits' => 100, 'reason' => 'because'])
        ->assertForbidden();
});

it('adjusts the allowance for an administrator', function (): void {
    config(['saas.ai.credits.monthly_allowance' => 100]);

    $company = workspace();
    $user = memberWith(['ai.use', 'ai.credits.manage'], $company)->refresh();

    actingAsMember($user, $company)
        ->post(route('ai.credits.adjust'), ['credits' => 400, 'reason' => 'Annual top-up'])
        ->assertRedirect();

    expect(AiCreditBalance::query()->where('company_id', $company->id)->value('allowance'))->toBe(500);
});

it('rejects a zero credit adjustment', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use', 'ai.credits.manage'], $company)->refresh();

    actingAsMember($user, $company)
        ->post(route('ai.credits.adjust'), ['credits' => 0, 'reason' => 'nothing'])
        ->assertSessionHasErrors('credits');
});

/*
|------------------------------------------------------------------------------
| Providers
|------------------------------------------------------------------------------
*/

it('forbids the provider screen without ai.providers.manage', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    actingAsMember($user, $company)->get(route('ai.providers.index'), inertiaHeaders())->assertForbidden();
});

it('never sends a stored provider key to the client', function (): void {
    $company = workspace();
    $user = memberWith(['ai.providers.manage'], $company)->refresh();

    app(SettingsRepository::class)->set(
        ProviderKeyStore::settingKey('openai'),
        'sk-super-secret',
        SettingsRepository::SCOPE_COMPANY,
        $company->id,
        true,
    );

    $response = actingAsMember($user, $company)
        ->get(route('ai.providers.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'ai/providers')
        ->assertJsonPath('props.mask', ProviderKeyStore::MASK);

    expect(json_encode($response->json('props')))->not->toContain('sk-super-secret');

    $openai = collect($response->json('props.providers'))->firstWhere('key', 'openai');
    expect($openai['is_configured'])->toBeTrue();
});

it('treats a blank or masked submission as leave unchanged', function (): void {
    $company = workspace();
    $user = memberWith(['ai.providers.manage'], $company)->refresh();
    $keys = app(ProviderKeyStore::class);

    $keys->put('openai', 'sk-original', $company->id);

    actingAsMember($user, $company)->patch(route('ai.providers.update'), [
        'keys' => ['openai' => ProviderKeyStore::MASK, 'gemini' => ''],
    ])->assertRedirect();

    expect($keys->resolve('openai', $company->id))->toBe('sk-original')
        ->and($keys->isSet('gemini', $company->id))->toBeFalse();
});

it('stores a new provider key encrypted', function (): void {
    $company = workspace();
    $user = memberWith(['ai.providers.manage'], $company)->refresh();

    actingAsMember($user, $company)
        ->patch(route('ai.providers.update'), ['keys' => ['openai' => 'sk-brand-new']])
        ->assertRedirect();

    expect(app(ProviderKeyStore::class)->resolve('openai', $company->id))->toBe('sk-brand-new');

    $this->assertDatabaseMissing('settings', ['value' => json_encode('sk-brand-new')]);
});

it('tests a provider connection without a live call', function (): void {
    Http::fake(['api.openai.com/*' => Http::response([
        'model' => 'gpt-4o',
        'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 1],
    ])]);

    $company = workspace();
    $user = memberWith(['ai.providers.manage'], $company)->refresh();
    app(ProviderKeyStore::class)->put('openai', 'sk-test', $company->id);

    actingAsMember($user, $company)
        ->postJson(route('ai.providers.test', 'openai'))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('model', 'gpt-4o');
});

it('reports a failed connection test', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Bad key']], 401)]);

    $company = workspace();
    $user = memberWith(['ai.providers.manage'], $company)->refresh();
    app(ProviderKeyStore::class)->put('openai', 'sk-bad', $company->id);

    actingAsMember($user, $company)
        ->postJson(route('ai.providers.test', 'openai'))
        ->assertStatus(422)
        ->assertJsonPath('ok', false);
});

it('keeps provider keys scoped to their workspace', function (): void {
    $other = workspace();
    $company = workspace();

    app(ProviderKeyStore::class)->put('openai', 'sk-mine', $company->id);

    config(['saas.ai.providers.openai.key' => null]);

    expect(app(ProviderKeyStore::class)->resolve('openai', $other->id))->toBeNull();
});
