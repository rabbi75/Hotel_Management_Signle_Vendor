<?php

declare(strict_types=1);

use App\Modules\AI\Services\ProviderKeyStore;
use App\Modules\Platform\Http\Middleware\ConfirmAdminPassword;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Support\SettingsSchema;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;

const AI_SETTINGS_TEST_SECRET = 'sk-platform-ai-0123456789';

function confirmAiSettingsPassword(): void
{
    session([ConfirmAdminPassword::SESSION_KEY => time()]);
}

it('redirects a guest to the login screen', function (): void {
    get(route('admin.settings.ai.index'))->assertRedirect(route('admin.login'));
});

it('challenges for the password before showing AI credentials', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.ai']);

    actingAsAdmin($admin)
        ->get(route('admin.settings.ai.index'))
        ->assertRedirect(route('admin.password.confirm'));
});

it('forbids a member without the AI manage permission', function (): void {
    $member = platformAdminWith(['platform.settings.view']);
    confirmAiSettingsPassword();

    actingAsAdmin($member)
        ->put(route('admin.settings.ai.update'), ['openai_api_key' => 'sk-nope'])
        ->assertForbidden();
});

it('stores a platform AI credential encrypted and never returns it to the client', function (): void {
    $admin = platformAdminWith(['platform.settings.view', 'platform.settings.ai']);
    confirmAiSettingsPassword();

    actingAsAdmin($admin)
        ->put(route('admin.settings.ai.update'), [
            'enabled' => true,
            'default_provider' => 'openai',
            'credits_enabled' => true,
            'monthly_credits' => 2500,
            'allow_tenant_keys' => true,
            'openai_api_key' => AI_SETTINGS_TEST_SECRET,
        ])
        ->assertRedirect();

    expect(setting('ai.openai_api_key'))->toBe(AI_SETTINGS_TEST_SECRET)
        ->and(setting('ai.default_provider'))->toBe('openai')
        ->and((int) setting('ai.monthly_credits'))->toBe(2500);

    $stored = Setting::query()->where('key', 'ai.openai_api_key')->firstOrFail();
    $raw = (string) DB::table('settings')->where('key', 'ai.openai_api_key')->value('value');

    expect($stored->is_encrypted)->toBeTrue()
        ->and($raw)->not->toContain(AI_SETTINGS_TEST_SECRET);

    $response = actingAsAdmin($admin)
        ->get(route('admin.settings.ai.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.settings.openai_api_key', SettingsSchema::MASK)
        ->assertJsonPath('props.secrets.openai_api_key', true)
        ->assertJsonPath('props.settings.default_provider', 'openai');

    expect($response->getContent())->not->toContain(AI_SETTINGS_TEST_SECRET);
});

it('falls back to the platform AI key when a tenant has none', function (): void {
    $company = workspace();

    app(SettingsRepository::class)->set(
        ProviderKeyStore::systemSettingKey('openai'),
        'sk-platform-shared',
        SettingsRepository::SCOPE_SYSTEM,
        null,
        true,
    );

    config(['saas.ai.providers.openai.key' => null]);

    expect(app(ProviderKeyStore::class)->resolve('openai', $company->id))->toBe('sk-platform-shared');
});

it('prefers a tenant key over the platform AI key', function (): void {
    $company = workspace();
    $keys = app(ProviderKeyStore::class);

    app(SettingsRepository::class)->set(
        ProviderKeyStore::systemSettingKey('openai'),
        'sk-platform-shared',
        SettingsRepository::SCOPE_SYSTEM,
        null,
        true,
    );

    $keys->put('openai', 'sk-tenant-own', $company->id);

    expect($keys->resolve('openai', $company->id))->toBe('sk-tenant-own');
});

it('tests a platform AI provider connection', function (): void {
    Http::fake(['api.openai.com/*' => Http::response([
        'model' => 'gpt-4o',
        'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 1],
    ])]);

    $admin = platformAdminWith(['platform.settings.ai']);
    confirmAiSettingsPassword();

    app(SettingsRepository::class)->set(
        ProviderKeyStore::systemSettingKey('openai'),
        'sk-test',
        SettingsRepository::SCOPE_SYSTEM,
        null,
        true,
    );

    actingAsAdmin($admin)
        ->postJson(route('admin.settings.ai.test', 'openai'))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('model', 'gpt-4o');
});
