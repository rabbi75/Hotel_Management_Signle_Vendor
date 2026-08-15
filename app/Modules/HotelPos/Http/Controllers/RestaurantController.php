<?php

declare(strict_types=1);

namespace App\Modules\HotelPos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HotelPos\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class RestaurantController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Restaurant::class);

        $restaurants = Restaurant::query()
            ->with('hotel')
            ->withCount('orders')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (Restaurant $restaurant): array => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'code' => $restaurant->code,
                'hotel' => $restaurant->hotel?->name,
                'is_active' => $restaurant->is_active,
                'orders_count' => $restaurant->orders_count,
            ]);

        return inertia('hotel-pos/index', [
            'restaurants' => $restaurants,
        ]);
    }
}
