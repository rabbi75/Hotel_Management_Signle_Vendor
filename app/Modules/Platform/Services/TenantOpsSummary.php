<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\AI\Models\AiGeneration;
use App\Modules\Company\Models\Company;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Housekeeping\Enums\HousekeepingTaskStatus;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Maintenance\Enums\MaintenanceRequestStatus;
use App\Modules\Maintenance\Models\MaintenanceRequest;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\Reservation\Models\Reservation;
use App\Modules\Workspace\Models\Workspace;
use App\Support\Settings\SettingsRepository;
use Carbon\CarbonImmutable;

/**
 * Operator-facing health and AI summary for a single tenant.
 */
class TenantOpsSummary
{
    public function __construct(protected SettingsRepository $settings) {}

    /**
     * @return array{
     *     hotels: int,
     *     rooms: int,
     *     workspaces: int,
     *     in_house_guests: int,
     *     arrivals_today: int,
     *     pending_housekeeping: int,
     *     open_maintenance: int,
     *     occupancy_rate: float
     * }
     */
    public function hotelFootprint(Company $company): array
    {
        $companyId = $company->id;
        $today = CarbonImmutable::today()->toDateString();

        $hotels = Hotel::query()->withoutCompanyScope()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->count();

        $roomsQuery = Room::query()->withoutCompanyScope()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at');

        $totalRooms = (clone $roomsQuery)->count();
        $occupiedRooms = (clone $roomsQuery)
            ->whereIn('status', ['occupied', 'reserved'])
            ->count();

        $inHouse = Reservation::query()->withoutCompanyScope()
            ->where('company_id', $companyId)
            ->where('status', ReservationStatus::CheckedIn->value)
            ->count();

        $arrivalsToday = Reservation::query()->withoutCompanyScope()
            ->where('company_id', $companyId)
            ->whereDate('check_in_date', $today)
            ->whereIn('status', [
                ReservationStatus::Pending->value,
                ReservationStatus::Confirmed->value,
                ReservationStatus::CheckedIn->value,
            ])
            ->count();

        $pendingHousekeeping = HousekeepingTask::query()->withoutCompanyScope()
            ->where('company_id', $companyId)
            ->whereIn('status', [
                HousekeepingTaskStatus::Pending->value,
                HousekeepingTaskStatus::InProgress->value,
            ])
            ->count();

        $openMaintenance = MaintenanceRequest::query()->withoutCompanyScope()
            ->where('company_id', $companyId)
            ->whereIn('status', [
                MaintenanceRequestStatus::Open->value,
                MaintenanceRequestStatus::InProgress->value,
                MaintenanceRequestStatus::OnHold->value,
            ])
            ->count();

        $workspaces = Workspace::query()->withoutCompanyScope()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->count();

        return [
            'hotels' => $hotels,
            'rooms' => $totalRooms,
            'workspaces' => $workspaces,
            'in_house_guests' => $inHouse,
            'arrivals_today' => $arrivalsToday,
            'pending_housekeeping' => $pendingHousekeeping,
            'open_maintenance' => $openMaintenance,
            'occupancy_rate' => $totalRooms > 0
                ? round(($occupiedRooms / $totalRooms) * 100, 1)
                : 0.0,
        ];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     period: string,
     *     allowance: int,
     *     used: int,
     *     reserved: int,
     *     available: int,
     *     generations_this_period: int,
     *     failed_this_period: int
     * }
     */
    public function aiSummary(Company $company): array
    {
        $period = AiCreditBalance::currentPeriod();

        $balance = AiCreditBalance::query()
            ->withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('period', $period)
            ->first();

        $generations = AiGeneration::query()
            ->withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('created_at', '>=', CarbonImmutable::parse($period.'-01')->startOfMonth())
            ->count();

        $failed = AiGeneration::query()
            ->withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('created_at', '>=', CarbonImmutable::parse($period.'-01')->startOfMonth())
            ->whereIn('status', ['failed', 'refused'])
            ->count();

        $enabled = $this->settings->getFrom(
            SettingsRepository::SCOPE_COMPANY,
            $company->id,
            'ai.enabled',
        );

        if ($enabled === null) {
            $enabled = $this->settings->getFrom(SettingsRepository::SCOPE_SYSTEM, null, 'ai.enabled');
        }

        if ($enabled === null) {
            $enabled = (bool) config('saas.ai.enabled', true);
        }

        return [
            'enabled' => (bool) $enabled,
            'period' => $period,
            'allowance' => (int) ($balance?->allowance ?? 0),
            'used' => (int) ($balance?->used ?? 0),
            'reserved' => (int) ($balance?->reserved ?? 0),
            'available' => $balance?->available() ?? 0,
            'generations_this_period' => $generations,
            'failed_this_period' => $failed,
        ];
    }
}
