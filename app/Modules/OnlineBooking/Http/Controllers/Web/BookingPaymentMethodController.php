<?php

declare(strict_types=1);

namespace App\Modules\OnlineBooking\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\OnlineBooking\Actions\SeedBookingPaymentMethods;
use App\Modules\OnlineBooking\Http\Requests\UpdateBookingPaymentMethodRequest;
use App\Modules\OnlineBooking\Http\Resources\BookingPaymentMethodResource;
use App\Modules\OnlineBooking\Models\BookingPaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingPaymentMethodController extends Controller
{
    public function index(Request $request, SeedBookingPaymentMethods $seed): Response
    {
        Gate::authorize('viewAny', BookingPaymentMethod::class);

        $companyId = current_company_id();

        if ($companyId !== null && BookingPaymentMethod::query()->count() === 0) {
            $seed->handle($companyId);
        }

        $methods = BookingPaymentMethod::query()
            ->orderByDesc('id')
            ->get();

        return Inertia::render('booking-payments/index', [
            'methods' => BookingPaymentMethodResource::collection($methods)->resolve($request),
        ]);
    }

    public function update(UpdateBookingPaymentMethodRequest $request, BookingPaymentMethod $bookingPaymentMethod): RedirectResponse
    {
        $bookingPaymentMethod->fill($request->validated());
        $bookingPaymentMethod->save();

        if ($bookingPaymentMethod->is_default) {
            BookingPaymentMethod::query()
                ->whereKeyNot($bookingPaymentMethod->id)
                ->update(['is_default' => false]);
        }

        return back()->with('success', __('Payment method updated.'));
    }
}
