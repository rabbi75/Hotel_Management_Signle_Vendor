<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Services;

use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\OnlineBooking\Models\BookingSetting;
use Carbon\CarbonImmutable;

class PublicRoomCatalogService
{
    public function __construct(
        protected AvailabilityQueryService $availability,
        protected ResolvePublicBookingProperty $properties,
    ) {}

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function defaultStay(?BookingSetting $setting): array
    {
        $advance = max(0, (int) ($setting?->min_advance_days ?? 0));
        $checkIn = CarbonImmutable::today()->addDays($advance)->startOfDay();

        return [$checkIn, $checkIn->addDay()];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forStay(Hotel $hotel, CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        $quotes = collect($this->availability->forHotel($hotel, $checkIn, $checkOut, includeUnavailable: true))
            ->keyBy('room_type_id');

        $types = RoomType::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->with(['facilities', 'media'])
            ->orderBy('base_price')
            ->get();

        $rows = $types->map(function (RoomType $type) use ($hotel, $quotes): array {
            $quote = $quotes->get($type->id);
            $available = is_array($quote) ? (int) $quote['available_rooms'] : 0;

            return [
                'room_type_id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'description' => $type->description,
                'bed_configuration' => $type->bed_configuration,
                'max_adults' => $type->max_adults,
                'max_children' => $type->max_children,
                'max_occupancy' => $type->max_occupancy,
                'image' => $type->getFirstMediaUrl('gallery') ?: null,
                'facilities' => $type->facilities
                    ->pluck('name')
                    ->filter()
                    ->values()
                    ->all(),
                'nightly_rate' => is_array($quote) ? (int) $quote['nightly_rate'] : $type->base_price,
                'nights' => is_array($quote) ? (int) $quote['nights'] : 1,
                'subtotal' => is_array($quote) ? (int) $quote['subtotal'] : $type->base_price,
                'currency' => $hotel->currency,
                'available_rooms' => $available,
            ];
        })->all();

        usort($rows, static function (array $left, array $right): int {
            $leftOpen = $left['available_rooms'] > 0 ? 0 : 1;
            $rightOpen = $right['available_rooms'] > 0 ? 0 : 1;

            return $leftOpen <=> $rightOpen ?: $left['nightly_rate'] <=> $right['nightly_rate'];
        });

        return $rows;
    }

    /**
     * @return array{hotel: Hotel, setting: BookingSetting, check_in: CarbonImmutable, check_out: CarbonImmutable, rooms: list<array<string, mixed>>}|null
     */
    public function forPublicProperty(?string $slug = null, ?CarbonImmutable $checkIn = null, ?CarbonImmutable $checkOut = null): ?array
    {
        if ($slug !== null && $slug !== '') {
            ['hotel' => $hotel, 'setting' => $setting] = $this->properties->bySlug($slug);
        } else {
            $setting = $this->properties->firstEnabled();

            if (! $setting instanceof BookingSetting) {
                return null;
            }

            $hotel = Hotel::query()
                ->withoutCompanyScope()
                ->withoutWorkspaceScope()
                ->whereKey($setting->hotel_id)
                ->where('is_active', true)
                ->first();

            if (! $hotel instanceof Hotel) {
                return null;
            }

            $this->properties->bindTenant($hotel);
        }

        [$defaultIn, $defaultOut] = $this->defaultStay($setting);
        $checkIn ??= $defaultIn;
        $checkOut ??= $defaultOut;

        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            $checkOut = $checkIn->addDay();
        }

        return [
            'hotel' => $hotel,
            'setting' => $setting,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'rooms' => $this->forStay($hotel, $checkIn, $checkOut),
        ];
    }

    public function confirmationUrl(int $hotelId, string $number): ?string
    {
        $setting = BookingSetting::query()
            ->withoutCompanyScope()
            ->withoutWorkspaceScope()
            ->where('hotel_id', $hotelId)
            ->where('is_enabled', true)
            ->whereNotNull('public_slug')
            ->first();

        if (! $setting instanceof BookingSetting || ! is_string($setting->public_slug) || $setting->public_slug === '') {
            return null;
        }

        return route('booking.confirmation', [
            'slug' => $setting->public_slug,
            'number' => $number,
        ]);
    }
}
