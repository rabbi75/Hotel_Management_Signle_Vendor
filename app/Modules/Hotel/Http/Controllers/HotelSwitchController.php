<?php

declare(strict_types=1);

namespace App\Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hotel\Models\Hotel;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Tenancy\CurrentHotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HotelSwitchController extends Controller
{
    public function __construct(
        protected CurrentHotel $currentHotel,
        protected NavigationBuilder $navigation,
    ) {}

    public function __invoke(Request $request, Hotel $hotel): RedirectResponse
    {
        Gate::authorize('switch', $hotel);

        $request->session()->put((string) config('saas.hotel.session_key'), $hotel->id);
        $this->currentHotel->set($hotel);

        $user = $request->user();
        if ($user !== null) {
            $this->navigation->flushFor($user);
        }

        return back()->with('success', __('You are now working in :hotel.', ['hotel' => $hotel->name]));
    }
}
