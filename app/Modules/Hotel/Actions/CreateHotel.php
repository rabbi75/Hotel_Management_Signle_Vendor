<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Actions;

use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Hotel\DTOs\HotelData;
use App\Modules\Hotel\Models\Hotel;
use Illuminate\Support\Str;

class CreateHotel
{
    public function __construct(protected SubscriptionLimits $limits) {}

    public function handle(HotelData $data): Hotel
    {
        $this->limits->ensure('hotels');

        $hotel = new Hotel($data->toAttributes());
        $hotel->slug = static::uniqueSlug($data->name);
        $hotel->save();

        return $hotel;
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 1;

        while (static::slugTaken($slug, $ignoreId)) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }

    protected static function slugTaken(string $slug, ?int $ignoreId): bool
    {
        $query = Hotel::query()->withTrashed()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}