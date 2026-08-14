<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Modules\Hotel\Models\Hotel;
use Closure;

/**
 * Holds the property the current request is working in.
 *
 * Mirrors {@see CurrentCompany}: a container singleton set by middleware and
 * read by controllers / filters. Never trust a bare request body for the
 * active hotel — session + company ownership are the source of truth.
 */
class CurrentHotel
{
    protected ?Hotel $hotel = null;

    public function get(): ?Hotel
    {
        return $this->hotel;
    }

    public function id(): ?int
    {
        return $this->hotel?->id;
    }

    public function set(?Hotel $hotel): void
    {
        $this->hotel = $hotel;
    }

    public function has(): bool
    {
        return $this->hotel instanceof Hotel;
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function scopeTo(?Hotel $hotel, Closure $callback): mixed
    {
        $previous = $this->hotel;
        $this->hotel = $hotel;

        try {
            return $callback();
        } finally {
            $this->hotel = $previous;
        }
    }

    public function forget(): void
    {
        $this->hotel = null;
    }
}
