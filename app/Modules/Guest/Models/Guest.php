<?php

declare(strict_types=1);

namespace App\Modules\Guest\Models;

use App\Modules\Guest\Enums\GuestGender;
use App\Modules\HotelOperations\Concerns\LogsHotelActivity;
use App\Modules\Hotel\Models\Hotel;
use App\Modules\Reservation\Models\Reservation;
use App\Support\Concerns\BelongsToCompany;
use App\Support\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $company_id
 * @property string $uuid
 * @property string $first_name
 * @property string $last_name
 */
class Guest extends Model implements HasMedia
{
    use BelongsToCompany, BelongsToWorkspace, InteractsWithMedia, LogsHotelActivity, SoftDeletes;

    protected $fillable = [
        'hotel_id', 'first_name', 'last_name', 'gender', 'date_of_birth',
        'phone', 'email', 'address', 'city', 'country', 'nationality',
        'id_type', 'id_number', 'emergency_contact_name', 'emergency_contact_phone',
        'notes', 'is_vip', 'is_blacklisted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => GuestGender::class,
            'date_of_birth' => 'date',
            'is_vip' => 'boolean',
            'is_blacklisted' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * @return BelongsTo<Hotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
        $this->addMediaCollection('id_document')->singleFile();
    }

    protected static function booted(): void
    {
        static::creating(function (self $guest): void {
            $guest->uuid ??= (string) Str::ulid();
        });
    }
}
