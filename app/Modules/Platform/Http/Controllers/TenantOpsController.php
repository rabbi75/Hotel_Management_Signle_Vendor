<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Services\CreditManager;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Company\Models\Company;
use App\Modules\Platform\Models\CompanySupportNote;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Operator actions on a tenant's AI policy, credits, and support notes.
 */
class TenantOpsController extends Controller
{
    public function __construct(
        protected CreditManager $credits,
        protected SettingsRepository $settings,
        protected SecurityLogger $security,
    ) {}

    public function adjustAiCredits(Request $request, Company $company): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.tenants.manage') ?? true, 403);

        $validated = $request->validate([
            'credits' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = $validated['reason'] ?? __('Adjusted by platform operator.');

        $this->credits->adjust($company->id, (int) $validated['credits'], null, $reason);

        $this->security->log(
            SecurityEvent::TenantAiCreditsAdjusted,
            $request->user('admin'),
            __('Adjusted AI credits for :name by :credits.', [
                'name' => $company->name,
                'credits' => $validated['credits'],
            ]),
            ['company_id' => $company->id, 'credits' => $validated['credits'], 'reason' => $reason],
        );

        return back()->with('success', __('AI credits updated.'));
    }

    public function toggleAi(Request $request, Company $company): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.tenants.manage') ?? true, 403);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['enabled'];

        $this->settings->set(
            'ai.enabled',
            $enabled,
            SettingsRepository::SCOPE_COMPANY,
            $company->id,
        );

        $this->security->log(
            SecurityEvent::TenantAiToggled,
            $request->user('admin'),
            $enabled
                ? __('Enabled AI for :name.', ['name' => $company->name])
                : __('Disabled AI for :name.', ['name' => $company->name]),
            ['company_id' => $company->id, 'enabled' => $enabled],
        );

        return back()->with('success', $enabled ? __('AI enabled for this tenant.') : __('AI disabled for this tenant.'));
    }

    public function storeNote(Request $request, Company $company): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.tenants.manage') ?? true, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]);

        $note = CompanySupportNote::query()->create([
            'company_id' => $company->id,
            'admin_id' => $request->user('admin')?->id,
            'body' => $validated['body'],
            'is_pinned' => (bool) ($validated['is_pinned'] ?? false),
        ]);

        $this->security->log(
            SecurityEvent::SupportNoteCreated,
            $request->user('admin'),
            __('Added a support note on :name.', ['name' => $company->name]),
            ['company_id' => $company->id, 'note_id' => $note->id],
        );

        return back()->with('success', __('Support note saved.'));
    }

    public function destroyNote(Request $request, Company $company, CompanySupportNote $note): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.tenants.manage') ?? true, 403);
        abort_unless($note->company_id === $company->id, 404);

        $note->delete();

        return back()->with('success', __('Support note deleted.'));
    }
}
