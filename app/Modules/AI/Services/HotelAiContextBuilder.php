<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use App\Modules\Guest\Models\Guest;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\HotelReports\Services\HotelDashboardService;
use App\Modules\Housekeeping\Enums\HousekeepingTaskStatus;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Maintenance\Models\MaintenanceRequest;
use App\Modules\Reservation\Models\Reservation;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Builds plain-text context blocks for hotel AI assists — never includes ID
 * document numbers, payment card data, or raw media paths.
 */
class HotelAiContextBuilder
{
    public function __construct(protected HotelDashboardService $dashboard) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function forAction(string $action, array $payload): string
    {
        return match ($action) {
            'reservation.staff_brief',
            'reservation.draft_confirmation',
            'reservation.draft_pre_arrival' => $this->reservation($payload),
            'guest.stay_summary',
            'guest.draft_welcome',
            'guest.vip_hints' => $this->guest($payload),
            'maintenance.triage' => $this->maintenance($payload),
            'housekeeping.floor_readiness' => $this->housekeeping($payload),
            'gm.daily_brief' => $this->dailyBrief($payload),
            'room_type.marketing_copy' => $this->roomType($payload),
            'hotel.booking_copy' => $this->hotel($payload),
            default => throw ValidationException::withMessages([
                'action' => __('Unknown AI assist action.'),
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function reservation(array $payload): string
    {
        $reservation = $this->findReservation($payload);
        $draft = is_array($payload['draft'] ?? null) ? $payload['draft'] : [];

        if ($reservation instanceof Reservation) {
            $reservation->loadMissing(['guest', 'hotel', 'roomType', 'room']);

            return $this->lines([
                'Reservation' => $reservation->number,
                'Status' => (string) ($reservation->status?->value ?? $reservation->status),
                'Hotel' => $reservation->hotel?->name,
                'Guest' => $reservation->guest?->fullName(),
                'VIP' => $reservation->guest?->is_vip ? 'yes' : 'no',
                'Blacklisted' => $reservation->guest?->is_blacklisted ? 'yes' : 'no',
                'Guest notes' => $reservation->guest?->notes,
                'Room type' => $reservation->roomType?->name,
                'Room' => $reservation->room?->number,
                'Check-in' => (string) $reservation->check_in_date,
                'Check-out' => (string) $reservation->check_out_date,
                'Adults' => (string) $reservation->adults,
                'Children' => (string) $reservation->children,
                'Source' => (string) ($reservation->booking_source?->value ?? $reservation->booking_source),
                'Special requests' => $reservation->special_requests,
                'Internal notes' => $reservation->notes,
                'Total (minor)' => (string) $reservation->total,
                'Paid (minor)' => (string) $reservation->paid_amount,
            ]);
        }

        return $this->lines([
            'Guest' => Arr::get($draft, 'guest_name'),
            'Hotel' => Arr::get($draft, 'hotel_name'),
            'Room type' => Arr::get($draft, 'room_type_name'),
            'Check-in' => Arr::get($draft, 'check_in_date'),
            'Check-out' => Arr::get($draft, 'check_out_date'),
            'Adults' => Arr::get($draft, 'adults'),
            'Children' => Arr::get($draft, 'children'),
            'Special requests' => Arr::get($draft, 'special_requests'),
            'Internal notes' => Arr::get($draft, 'notes'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function guest(array $payload): string
    {
        $guest = $this->findGuest($payload);

        if (! $guest instanceof Guest) {
            throw ValidationException::withMessages([
                'subject_id' => __('A guest is required for this assist.'),
            ]);
        }

        $guest->loadMissing(['reservations' => static fn ($q) => $q->with('roomType')->latest('check_in_date')->limit(8)]);

        $stays = $guest->reservations->map(static function (Reservation $reservation): string {
            return sprintf(
                '%s | %s → %s | %s | room type: %s',
                $reservation->number,
                $reservation->check_in_date,
                $reservation->check_out_date,
                (string) ($reservation->status?->value ?? $reservation->status),
                $reservation->roomType?->name ?? 'n/a',
            );
        })->implode("\n");

        return $this->lines([
            'Name' => $guest->fullName(),
            'Email' => $guest->email,
            'Phone' => $guest->phone,
            'Nationality' => $guest->nationality,
            'City / country' => trim(implode(', ', array_filter([$guest->city, $guest->country]))),
            'VIP' => $guest->is_vip ? 'yes' : 'no',
            'Blacklisted' => $guest->is_blacklisted ? 'yes' : 'no',
            'Notes' => $guest->notes,
            'Recent stays' => $stays !== '' ? $stays : 'none',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function maintenance(array $payload): string
    {
        $request = $this->findMaintenance($payload);
        $draft = is_array($payload['draft'] ?? null) ? $payload['draft'] : [];

        if ($request instanceof MaintenanceRequest) {
            $request->loadMissing(['hotel', 'room']);

            return $this->lines([
                'Number' => $request->number,
                'Hotel' => $request->hotel?->name,
                'Room' => $request->room?->number,
                'Title' => $request->title,
                'Description' => $request->description,
                'Category' => (string) ($request->category?->value ?? $request->category),
                'Priority' => (string) ($request->priority?->value ?? $request->priority),
                'Blocks room' => $request->blocks_room ? 'yes' : 'no',
            ]);
        }

        return $this->lines([
            'Title' => Arr::get($draft, 'title'),
            'Description' => Arr::get($draft, 'description'),
            'Category' => Arr::get($draft, 'category'),
            'Priority' => Arr::get($draft, 'priority'),
            'Blocks room' => Arr::get($draft, 'blocks_room') ? 'yes' : 'no',
            'Room' => Arr::get($draft, 'room_label'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function housekeeping(array $payload): string
    {
        $hotelId = isset($payload['hotel_id']) && is_numeric($payload['hotel_id'])
            ? (int) $payload['hotel_id']
            : current_hotel_id();

        $tasks = HousekeepingTask::query()
            ->with(['room', 'hotel'])
            ->whereIn('status', [
                HousekeepingTaskStatus::Pending->value,
                HousekeepingTaskStatus::InProgress->value,
            ])
            ->when($hotelId !== null, static fn ($q) => $q->where('hotel_id', $hotelId))
            ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 else 4 end")
            ->limit(40)
            ->get();

        if ($tasks->isEmpty()) {
            return 'Hotel id: '.($hotelId ?? 'all')."\nOpen housekeeping tasks: none.";
        }

        $lines = $tasks->map(static function (HousekeepingTask $task): string {
            return sprintf(
                '%s | room %s | %s | priority %s | type %s | %s',
                $task->number,
                $task->room?->number ?? 'n/a',
                (string) ($task->status?->value ?? $task->status),
                (string) ($task->priority?->value ?? $task->priority),
                (string) ($task->task_type?->value ?? $task->task_type),
                $task->instructions ?: 'no instructions',
            );
        })->implode("\n");

        return "Hotel id: ".($hotelId ?? 'all')."\nOpen tasks:\n".$lines;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function dailyBrief(array $payload): string
    {
        $hotelId = isset($payload['hotel_id']) && is_numeric($payload['hotel_id'])
            ? (int) $payload['hotel_id']
            : current_hotel_id();

        $snapshot = $this->dashboard->snapshot($hotelId);

        $blockingMaintenance = MaintenanceRequest::query()
            ->with('room')
            ->where('blocks_room', true)
            ->whereIn('status', ['open', 'in_progress', 'on_hold'])
            ->when($hotelId !== null, static fn ($q) => $q->where('hotel_id', $hotelId))
            ->limit(15)
            ->get()
            ->map(static fn (MaintenanceRequest $item): string => sprintf(
                '%s | room %s | %s | %s',
                $item->number,
                $item->room?->number ?? 'n/a',
                $item->title,
                (string) ($item->priority?->value ?? $item->priority),
            ))
            ->implode("\n");

        return $this->lines([
            'Date' => (string) ($snapshot['date'] ?? now()->toDateString()),
            'Occupancy rate' => (string) ($snapshot['occupancy_rate'] ?? ''),
            'Occupied rooms' => (string) ($snapshot['occupied_rooms'] ?? ''),
            'Total rooms' => (string) ($snapshot['total_rooms'] ?? ''),
            'Available rooms' => (string) ($snapshot['available_rooms'] ?? ''),
            'In-house guests' => (string) ($snapshot['in_house_guests'] ?? ''),
            'Arrivals today' => (string) ($snapshot['arrivals_today'] ?? ''),
            'Departures today' => (string) ($snapshot['departures_today'] ?? ''),
            'Revenue today (minor)' => (string) ($snapshot['revenue_today'] ?? ''),
            'Currency' => (string) ($snapshot['currency'] ?? ''),
            'Pending housekeeping' => (string) ($snapshot['pending_housekeeping'] ?? ''),
            'Open maintenance' => (string) ($snapshot['open_maintenance'] ?? ''),
            'Room-blocking maintenance' => $blockingMaintenance !== '' ? $blockingMaintenance : 'none',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function roomType(array $payload): string
    {
        $roomType = $this->findRoomType($payload);
        $draft = is_array($payload['draft'] ?? null) ? $payload['draft'] : [];

        if ($roomType instanceof RoomType) {
            $roomType->loadMissing('facilities');

            return $this->lines([
                'Name' => $roomType->name,
                'Code' => $roomType->code,
                'Base price (minor)' => (string) $roomType->base_price,
                'Max adults' => (string) $roomType->max_adults,
                'Max children' => (string) $roomType->max_children,
                'Max occupancy' => (string) $roomType->max_occupancy,
                'Bed configuration' => $roomType->bed_configuration,
                'Facilities' => $roomType->facilities->pluck('name')->filter()->implode(', '),
                'Existing description' => $roomType->description,
            ]);
        }

        return $this->lines([
            'Name' => Arr::get($draft, 'name'),
            'Code' => Arr::get($draft, 'code'),
            'Base price (minor)' => Arr::get($draft, 'base_price'),
            'Max adults' => Arr::get($draft, 'max_adults'),
            'Max children' => Arr::get($draft, 'max_children'),
            'Max occupancy' => Arr::get($draft, 'max_occupancy'),
            'Bed configuration' => Arr::get($draft, 'bed_configuration'),
            'Facilities' => Arr::get($draft, 'facilities'),
            'Existing description' => Arr::get($draft, 'description'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hotel(array $payload): string
    {
        $hotel = $this->findHotel($payload);
        $draft = is_array($payload['draft'] ?? null) ? $payload['draft'] : [];
        $focus = (string) ($payload['focus'] ?? Arr::get($draft, 'focus') ?? 'description');

        if ($hotel instanceof Hotel) {
            return $this->lines([
                'Focus' => $focus,
                'Name' => $hotel->name,
                'City' => $hotel->city,
                'Country' => $hotel->country,
                'Address' => $hotel->address,
                'Star rating' => (string) ($hotel->star_rating ?? ''),
                'Existing description' => $hotel->description,
                'Existing policies' => $hotel->policies,
                'Check-in time' => (string) ($hotel->check_in_time ?? ''),
                'Check-out time' => (string) ($hotel->check_out_time ?? ''),
            ]);
        }

        return $this->lines([
            'Focus' => $focus,
            'Name' => Arr::get($draft, 'name'),
            'City' => Arr::get($draft, 'city'),
            'Country' => Arr::get($draft, 'country'),
            'Address' => Arr::get($draft, 'address'),
            'Existing description' => Arr::get($draft, 'description'),
            'Existing policies' => Arr::get($draft, 'policies'),
            'Check-in time' => Arr::get($draft, 'check_in_time'),
            'Check-out time' => Arr::get($draft, 'check_out_time'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function findReservation(array $payload): ?Reservation
    {
        $id = $payload['subject_id'] ?? null;

        return is_numeric($id) ? Reservation::query()->find((int) $id) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function findGuest(array $payload): ?Guest
    {
        $id = $payload['subject_id'] ?? null;

        if ($id === null || $id === '') {
            return null;
        }

        return Guest::query()
            ->where(function ($query) use ($id): void {
                $query->where('id', $id)->orWhere('uuid', (string) $id);
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function findMaintenance(array $payload): ?MaintenanceRequest
    {
        $id = $payload['subject_id'] ?? null;

        return is_numeric($id) ? MaintenanceRequest::query()->find((int) $id) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function findRoomType(array $payload): ?RoomType
    {
        $id = $payload['subject_id'] ?? null;

        return is_numeric($id) ? RoomType::query()->find((int) $id) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function findHotel(array $payload): ?Hotel
    {
        $id = $payload['subject_id'] ?? null;

        return is_numeric($id) ? Hotel::query()->find((int) $id) : null;
    }

    /**
     * @param  array<string, scalar|null>  $fields
     */
    protected function lines(array $fields): string
    {
        $out = [];

        foreach ($fields as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $out[] = $label.': '.$value;
        }

        return $out === [] ? 'No context provided.' : implode("\n", $out);
    }
}
