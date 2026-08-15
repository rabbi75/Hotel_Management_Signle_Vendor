<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Models;

use App\Modules\Reservation\Models\Reservation;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 */
class Customer extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $customer): void {
            $customer->uuid ??= (string) Str::ulid();
        });
    }
}
