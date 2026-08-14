<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Gateways\GatewayManager;
use App\Modules\Billing\Http\Requests\StorePaymentMethodRequest;
use App\Modules\Billing\Http\Resources\PaymentMethodResource;
use App\Modules\Billing\Models\PaymentMethod;
use App\Modules\Company\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function __construct(
        protected GatewayManager $gateways,
        protected SecurityLogger $security,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PaymentMethod::class);

        return Inertia::render('billing/payment-methods', [
            'methods' => PaymentMethodResource::collection(
                PaymentMethod::query()->orderByDesc('is_default')->orderByDesc('id')->get(),
            )->resolve($request),
            'gateway' => $this->gateways->defaultDriver(),
            'can' => [
                'manage' => Gate::allows('create', PaymentMethod::class),
            ],
        ]);
    }

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $company = $this->company();

        try {
            $remote = $this->gateways->driver()->addPaymentMethod($company, (string) $request->string('token'));
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $method = DB::transaction(function () use ($remote, $company, $request): PaymentMethod {
            $makeDefault = $request->boolean('make_default', true)
                || ! PaymentMethod::query()->where('is_default', true)->exists();

            if ($makeDefault) {
                PaymentMethod::query()->where('is_default', true)->update(['is_default' => false]);
            }

            $method = new PaymentMethod([...$remote->toAttributes(), 'is_default' => $makeDefault]);
            $method->company_id = $company->id;
            $method->save();

            return $method;
        });

        $this->security->log(
            SecurityEvent::PaymentMethodAdded,
            $request->user(),
            __('Payment method added.'),
            ['payment_method_id' => $method->id, 'gateway' => $method->gateway],
        );

        return back()->with('success', __('Payment method added.'));
    }

    public function makeDefault(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        Gate::authorize('update', $paymentMethod);

        DB::transaction(function () use ($paymentMethod): void {
            PaymentMethod::query()->where('is_default', true)->update(['is_default' => false]);
            $paymentMethod->update(['is_default' => true]);
        });

        return back()->with('success', __('Default payment method updated.'));
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        Gate::authorize('delete', $paymentMethod);

        $wasDefault = $paymentMethod->is_default;
        $paymentMethod->delete();

        // Never leave a workspace with cards on file but none of them default.
        if ($wasDefault) {
            $next = PaymentMethod::query()->latest('id')->first();
            $next?->update(['is_default' => true]);
        }

        $this->security->log(
            SecurityEvent::PaymentMethodRemoved,
            $request->user(),
            __('Payment method removed.'),
            ['gateway' => $paymentMethod->gateway],
        );

        return back()->with('success', __('Payment method removed.'));
    }

    protected function company(): Company
    {
        $company = current_company();

        abort_if(! $company instanceof Company, 403, __('No active workspace.'));

        return $company;
    }
}
