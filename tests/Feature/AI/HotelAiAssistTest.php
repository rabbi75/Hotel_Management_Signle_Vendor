<?php

declare(strict_types=1);

use App\Modules\AI\Actions\EnsureHotelPromptPack;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\AI\Models\AiPromptTemplate;
use App\Modules\AI\Support\HotelPromptPack;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'saas.ai.default' => 'openai',
        'saas.ai.providers.openai.key' => 'sk-openai-test',
    ]);
});

it('registers Assistants navigation destinations', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    actingAsMember($user, $company)
        ->get(route('ai.brief.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'ai/brief');
});

it('seeds the hotel prompt pack for a company', function (): void {
    $company = workspace();

    app(EnsureHotelPromptPack::class)->handle($company);

    $slugs = AiPromptTemplate::query()
        ->withoutGlobalScope(CompanyScope::class)
        ->where('company_id', $company->id)
        ->where('category', HotelPromptPack::CATEGORY)
        ->pluck('slug')
        ->all();

    expect($slugs)->toEqualCanonicalizing(HotelPromptPack::slugs());
});

it('installs missing hotel templates when the brief page is opened', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    expect(
        AiPromptTemplate::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('slug', 'gm-daily-brief')
            ->exists(),
    )->toBeFalse();

    actingAsMember($user, $company)->get(route('ai.brief.index'), inertiaHeaders())->assertOk();

    expect(
        AiPromptTemplate::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('slug', 'gm-daily-brief')
            ->exists(),
    )->toBeTrue();
});

it('streams a reservation staff brief from draft context', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(
        "data: {\"choices\":[{\"delta\":{\"content\":\"- Late check-in\"}}]}\n\n"
        ."data: {\"choices\":[{\"delta\":{\"content\":\"\\n- Extra pillows\"},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":20,\"completion_tokens\":10}}\n\n"
        ."data: [DONE]\n\n",
        200,
        ['Content-Type' => 'text/event-stream'],
    )]);

    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    app(EnsureHotelPromptPack::class)->handle($company);

    $response = actingAsMember($user, $company)->post(route('ai.assist.stream'), [
        'action' => 'reservation.staff_brief',
        'draft' => [
            'guest_name' => 'Ada Lovelace',
            'special_requests' => 'Late check-in and extra pillows',
            'check_in_date' => '2026-08-12',
            'check_out_date' => '2026-08-14',
        ],
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $body = $response->streamedContent();

    expect($body)->toContain('event: delta')
        ->and($body)->toContain('Late check-in')
        ->and($body)->toContain('event: done');

    expect(AiGeneration::query()->firstOrFail()->output)->toContain('Late check-in');
});

it('streams a GM daily brief', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(
        "data: {\"choices\":[{\"delta\":{\"content\":\"Occupancy is steady.\"},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":30,\"completion_tokens\":8}}\n\n"
        ."data: [DONE]\n\n",
        200,
        ['Content-Type' => 'text/event-stream'],
    )]);

    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    app(EnsureHotelPromptPack::class)->handle($company);

    $response = actingAsMember($user, $company)->post(route('ai.assist.stream'), [
        'action' => 'gm.daily_brief',
    ]);

    $response->assertOk();
    expect($response->streamedContent())->toContain('Occupancy is steady.');
});

it('rejects an unknown assist action', function (): void {
    $company = workspace();
    $user = memberWith(['ai.use'], $company)->refresh();

    actingAsMember($user, $company)
        ->postJson(route('ai.assist.stream'), ['action' => 'not.a.real.action'])
        ->assertStatus(422);
});
