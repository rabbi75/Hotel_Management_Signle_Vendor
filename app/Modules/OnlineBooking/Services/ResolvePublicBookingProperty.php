<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Services;

use App\Modules\Company\Models\Company;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\OnlineBooking\Models\BookingSetting;
use App\Modules\Workspace\Models\Workspace;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Tenancy\CurrentWorkspace;

/**
 * Resolves a publicly bookable hotel from its booking slug and binds tenancy
 * so subsequent hotel/reservation queries are scoped correctly for a guest
 * who has no session workspace.
 */
class ResolvePublicBookingProperty
{
    public function __construct(
        protected CurrentCompany $company,
        protected CurrentWorkspace $workspace,
    ) {}

    public function firstEnabled(): ?BookingSetting
    {
        return BookingSetting::query()
            ->withoutCompanyScope()
            ->withoutWorkspaceScope()
            ->where('is_enabled', true)
            ->whereNotNull('public_slug')
            ->where('public_slug', '!=', '')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{hotel: Hotel, setting: BookingSetting}
     */
    public function bySlug(string $slug): array
    {
        $setting = BookingSetting::query()
            ->withoutCompanyScope()
            ->withoutWorkspaceScope()
            ->where('public_slug', $slug)
            ->where('is_enabled', true)
            ->first();

        abort_unless($setting instanceof BookingSetting, 404);

        $hotel = Hotel::query()
            ->withoutCompanyScope()
            ->withoutWorkspaceScope()
            ->whereKey($setting->hotel_id)
            ->where('is_active', true)
            ->first();

        abort_unless($hotel instanceof Hotel, 404);

        $this->bindTenant($hotel);

        return ['hotel' => $hotel, 'setting' => $setting];
    }

    public function bindTenant(Hotel $hotel): void
    {
        $company = Company::query()->find($hotel->company_id);

        if ($company instanceof Company) {
            $this->company->set($company);
        }

        $workspaceId = $hotel->getAttribute('workspace_id');

        if (! is_numeric($workspaceId)) {
            return;
        }

        $workspace = Workspace::query()
            ->withoutCompanyScope()
            ->find((int) $workspaceId);

        if ($workspace instanceof Workspace) {
            $this->workspace->set($workspace);
        }
    }

    public function url(): ?string
    {
        $setting = $this->firstEnabled();

        if (! $setting instanceof BookingSetting || ! is_string($setting->public_slug) || $setting->public_slug === '') {
            return null;
        }

        return route('booking.index');
    }
}
