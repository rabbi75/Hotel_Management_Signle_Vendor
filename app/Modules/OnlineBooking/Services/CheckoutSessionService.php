<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Services;

use Illuminate\Http\Request;

class CheckoutSessionService
{
    public const KEY = 'public_booking_checkout';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(Request $request, array $payload): void
    {
        $request->session()->put(self::KEY, $payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(Request $request, string $slug): ?array
    {
        $payload = $request->session()->get(self::KEY);

        if (! is_array($payload) || ($payload['slug'] ?? null) !== $slug) {
            return null;
        }

        return $payload;
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }
}
