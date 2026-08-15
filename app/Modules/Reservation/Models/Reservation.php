<?php

declare(strict_types=1);

namespace App\Modules\Reservation\Models;

use App\Modules\Folio\Models\GuestFolio;
use App\Modules\Guest\Models\Guest;
use App\Modules\Hotel\Models\Bed;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Hotel\Models\Room;
use App\Modules\Hotel\Models\RoomType;
use App\Modules\OnlineBooking\Enums\PaymentStatus;
use App\Modules\OnlineBooking\Models\BookingPaymentMethod;
use App\Modules\OnlineBooking\Models\Customer;
use App\Modules\Reservation\Enums\BookingSource;
use App\Modules\Reservation\Enums\ReservationStatus;
use App\Modules\HotelOperations\Concerns\LogsHotelActivity;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property int $hotel_id
 * @property string $number
 * @property int $guest_id
 * @property CarbonImmutable $check_in_date
 * @property CarbonImmutable $check_out_date
 * @property ReservationStatus $status
 */
class Reservation extends Model
{
    use BelongsToCompany, BelongsToWorkspace, LogsHotelActivity, SoftDeletes;

    protected $fillable = [
        'hotel_id', 'number', 'guest_id', 'customer_id', 'room_id', 'bed_id', 'room_type_id',
        'check_in_date', 'check_out_date', 'checked_in_at', 'checked_out_at',
        'adults', 'children', 'rooms_count', 'booking_source', 'payment_method_id',
        'external_reference', 'channel_metadata',
        'special_requests', 'notes', 'discount', 'tax', 'total',
        'paid_amount', 'due_amount', 'payment_status', 'payment_reference', 'paid_at', 'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'checked_in_at' => 'immutable_datetime',
            'checked_out_at' => 'immutable_datetime',
            'adults' => 'integer',
            'children' => 'integer',
            'rooms_count' => 'integer',
            'booking_source' => BookingSource::class,
            'channel_metadata' => 'array',
            'discount' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'due_amount' => 'integer',
            'payment_status' => PaymentStatus::class,
            'paid_at' => 'immutable_datetime',
            'status' => ReservationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Hotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<BookingPaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(BookingPaymentMethod::class, 'payment_method_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Bed, $this>
     */
    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * @return HasOne<GuestFolio, $this>
     */
    public function guestFolio(): HasOne
    {
        return $this->hasOne(GuestFolio::class);
    }

    public function nights(): int
    {
        return max(1, (int) $this->check_in_date->diffInDays($this->check_out_date));
    }
}
