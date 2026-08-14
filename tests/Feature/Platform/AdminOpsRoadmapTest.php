<?php

declare(strict_types=1);

use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Models\SecurityLog;
use App\Modules\Platform\Models\CompanySupportNote;
use App\Modules\Platform\Models\EmailTemplate;
use App\Support\Settings\SettingsRepository;

it('shows hotel, ai and support notes on the tenant dossier', function (): void {
    $company = workspace();
    $admin = platformAdminWith(['platform.tenants.view', 'platform.tenants.manage']);

    AiCreditBalance::query()->create([
        'company_id' => $company->id,
        'period' => AiCreditBalance::currentPeriod(),
        'allowance' => 5000,
        'used' => 250,
        'reserved' => 0,
    ]);

    actingAsAdmin($admin)
        ->get(route('admin.tenants.show', $company), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.hotel.hotels', 0)
        ->assertJsonPath('props.ai.used', 250)
        ->assertJsonPath('props.ai.available', 4750)
        ->assertJsonPath('props.support_notes', []);
});

it('lets an operator grant credits and disable ai for a tenant', function (): void {
    $company = workspace();
    $admin = platformAdminWith(['platform.tenants.view', 'platform.tenants.manage']);

    AiCreditBalance::query()->create([
        'company_id' => $company->id,
        'period' => AiCreditBalance::currentPeriod(),
        'allowance' => 1000,
        'used' => 0,
        'reserved' => 0,
    ]);

    actingAsAdmin($admin)
        ->post(route('admin.tenants.ai.credits', $company), [
            'credits' => 500,
            'reason' => 'Support goodwill',
        ])
        ->assertRedirect();

    expect((int) AiCreditBalance::query()->withoutCompanyScope()->where('company_id', $company->id)->value('allowance'))
        ->toBe(1500);

    actingAsAdmin($admin)
        ->patch(route('admin.tenants.ai.toggle', $company), ['enabled' => false])
        ->assertRedirect();

    $enabled = app(SettingsRepository::class)->getFrom(
        SettingsRepository::SCOPE_COMPANY,
        $company->id,
        'ai.enabled',
    );

    expect($enabled)->toBeFalse()
        ->and(SecurityLog::query()->where('event', SecurityEvent::TenantAiCreditsAdjusted)->exists())->toBeTrue()
        ->and(SecurityLog::query()->where('event', SecurityEvent::TenantAiToggled)->where('company_id', $company->id)->exists())->toBeTrue();
});

it('stores and deletes support notes on a tenant', function (): void {
    $company = workspace();
    $admin = platformAdminWith(['platform.tenants.manage']);

    actingAsAdmin($admin)
        ->post(route('admin.tenants.notes.store', $company), [
            'body' => 'Called about billing — will follow up Friday.',
            'is_pinned' => true,
        ])
        ->assertRedirect();

    $note = CompanySupportNote::query()->where('company_id', $company->id)->first();

    expect($note)->not->toBeNull()
        ->and($note->is_pinned)->toBeTrue()
        ->and($note->admin_id)->toBe($admin->id);

    actingAsAdmin($admin)
        ->delete(route('admin.tenants.notes.destroy', [$company, $note]))
        ->assertRedirect();

    expect(CompanySupportNote::query()->whereKey($note->id)->exists())->toBeFalse();
});

it('lists cross-tenant ai usage for operators', function (): void {
    $company = workspace(null, ['name' => 'Harbor Inn Group']);
    $admin = platformAdminWith(['platform.tenants.view']);

    AiCreditBalance::query()->create([
        'company_id' => $company->id,
        'period' => AiCreditBalance::currentPeriod(),
        'allowance' => 2000,
        'used' => 400,
        'reserved' => 50,
    ]);

    actingAsAdmin($admin)
        ->get(route('admin.ai.usage'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.rows.0.name', 'Harbor Inn Group')
        ->assertJsonPath('props.rows.0.used', 400);
});

it('renders the platform audit log', function (): void {
    $admin = platformAdminWith(['platform.audit.view']);

    actingAsAdmin($admin)
        ->get(route('admin.audit.index'), inertiaHeaders())
        ->assertOk();
});

it('broadcasts a platform announcement', function (): void {
    $company = workspace();
    $admin = platformAdminWith(['platform.announcements.manage']);

    actingAsAdmin($admin)
        ->post(route('admin.announcements.store'), [
            'heading' => 'Scheduled maintenance',
            'message' => 'Console will be briefly unavailable Sunday night.',
            'level' => 'warning',
            'audience' => 'active',
        ])
        ->assertRedirect();

    expect(SecurityLog::query()->where('event', SecurityEvent::AnnouncementBroadcast)->exists())->toBeTrue();
    expect($company->owner)->not->toBeNull();
});

it('seeds and updates lifecycle email templates', function (): void {
    $admin = platformAdminWith(['platform.email_templates.manage']);

    actingAsAdmin($admin)
        ->get(route('admin.email-templates.index'), inertiaHeaders())
        ->assertOk();

    $template = EmailTemplate::query()->where('key', 'welcome')->firstOrFail();

    actingAsAdmin($admin)
        ->put(route('admin.email-templates.update', $template), [
            'subject' => 'Hello {{user_name}}',
            'body' => 'Welcome aboard, {{user_name}}.',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($template->fresh()->subject)->toBe('Hello {{user_name}}');
});
