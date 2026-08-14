<?php

declare(strict_types=1);

namespace App\Modules\Reservation\DTOs;

use App\Modules\Reservation\Enums\BookingSource;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class ReservationData extends Data
{
    public const FIELDS = [
        'hotel_id', 'guest_id', 'room_id', 'bed_id', 'room_type_id',
        'check_in_date', 'check_out_date', 'adults', 'children', 'rooms_count',
        'booking_source', 'external_reference', 'channel_metadata', 'special_requests', 'notes', 'discount', 'tax',
        'total', 'paid_amount', 'status',
    ];

    /**
     * @param  list<string>  $provided
     */
    public function __construct(
        public int $hotelId,
        public int $guestId,
        public string $checkInDate,
        public string $checkOutDate,
        public ?int $roomId = null,
        public ?int $bedId = null,
        public ?int $roomTypeId = null,
        public int $adults = 1,
        public int $children = 0,
        public int $roomsCount = 1,
        public BookingSource $bookingSource = BookingSource::WalkIn,
        public ?string $externalReference = null,
        public array $channelMetadata = [],
        public ?string $specialRequests = null,
        public ?string $notes = null,
        public int $discount = 0,
        public int $tax = 0,
        public int $total = 0,
        public int $paidAmount = 0,
        public ReservationStatus $status = ReservationStatus::Pending,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(self::FIELDS, static fn (string $f): bool => $request->has($f)));

        $nullableInt = static fn (mixed $v): ?int => is_numeric($v) ? (int) $v : null;
        $source = (string) $request->input('booking_source', BookingSource::WalkIn->value);
        $status = (string) $request->input('status', ReservationStatus::Pending->value);

        $total = (int) $request->input('total', 0);
        $paid = (int) $request->input('paid_amount', 0);

        return new self(
            hotelId: (int) $request->input('hotel_id'),
            guestId: (int) $request->input('guest_id'),
            checkInDate: (string) $request->input('check_in_date'),
            checkOutDate: (string) $request->input('check_out_date'),
            roomId: $nullableInt($request->input('room_id')),
            bedId: $nullableInt($request->input('bed_id')),
            roomTypeId: $nullableInt($request->input('room_type_id')),
            adults: (int) $request->input('adults', 1),
            children: (int) $request->input('children', 0),
            roomsCount: (int) $request->input('rooms_count', 1),
            bookingSource: BookingSource::tryFrom($source) ?? BookingSource::WalkIn,
            specialRequests: $request->input('special_requests') ?: null,
            notes: $request->input('notes') ?: null,
            discount: (int) $request->input('discount', 0),
            tax: (int) $request->input('tax', 0),
            total: $total,
            paidAmount: $paid,
            status: ReservationStatus::tryFrom($status) ?? ReservationStatus::Pending,
            provided: $provided,
        );
    }

    public function dueAmount(): int
    {
        return max(0, $this->total - $this->paidAmount);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'hotel_id' => $this->hotelId,
            'guest_id' => $this->guestId,
            'room_id' => $this->roomId,
            'bed_id' => $this->bedId,
            'room_type_id' => $this->roomTypeId,
            'check_in_date' => $this->checkInDate,
            'check_out_date' => $this->checkOutDate,
            'adults' => $this->adults,
            'children' => $this->children,
            'rooms_count' => $this->roomsCount,
            'booking_source' => $this->bookingSource,
            'external_reference' => $this->externalReference,
            'channel_metadata' => $this->channelMetadata !== [] ? $this->channelMetadata : null,
            'special_requests' => $this->specialRequests,
            'notes' => $this->notes,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
            'paid_amount' => $this->paidAmount,
            'due_amount' => $this->dueAmount(),
            'status' => $this->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(): array
    {
        $attrs = array_intersect_key($this->toAttributes(), array_flip($this->provided));

        if (isset($attrs['total']) || isset($attrs['paid_amount'])) {
            $attrs['due_amount'] = $this->dueAmount();
        }

        return $attrs;
    }
}
