<?php

declare(strict_types=1);

use App\Modules\Billing\Http\Controllers\CheckoutController;
use App\Modules\Billing\Http\Controllers\InvoiceController;
use App\Modules\Billing\Http\Controllers\PaymentMethodController;
use App\Modules\Billing\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Billing routes
|------------------------------------------------------------------------------
|
| Fixed segments (`plans`, `invoices`, `admin`) are declared before the
| `{subscription}` wildcard so none of them is swallowed by it.
|
| Anything that moves money or touches a credential sits behind
| `password.confirm`.
|
*/

Route::middleware(['auth', 'verified'])->prefix('billing')->name('billing.')->group(function (): void {

    Route::get('/', [SubscriptionController::class, 'index'])->name('index');
    Route::get('plans', [SubscriptionController::class, 'plans'])->name('plans');

    // -- Subscription lifecycle ---------------------------------------------
    //
    // Anything that moves money is blocked while impersonating: a charge or a
    // cancellation made from inside a customer's session must never be
    // attributable to the customer.
    // -- Hosted checkout -----------------------------------------------------
    //
    // The return leg is `signed`: the parameters name a plan and a workspace,
    // and without a signature anyone could grant themselves a subscription by
    // typing the URL. The signature proves the parameters are ours; the
    // driver's own verification proves the payment happened. Both are required.
    //
    // Processors append their own reference to the URL they were given, and a
    // signature computed over the whole query string would reject it. The
    // ignored keys are named rather than the check relaxed: everything that
    // decides what is granted — gateway, plan, interval, company, coupon — is
    // still covered, and a forged reference only reaches a server-side
    // verification call that will not recognise it.
    Route::middleware([
        'deny.impersonating',
        'signed:'.implode(',', CheckoutController::CALLBACK_PARAMETERS),
    ])->group(function (): void {
        Route::get('checkout/return', [CheckoutController::class, 'return'])->name('checkout.return');
    });

    // Braintree renders its own form in our page; nothing appends to this one.
    Route::middleware(['deny.impersonating', 'signed'])->group(function (): void {
        Route::get('checkout/braintree', [CheckoutController::class, 'braintree'])->name('checkout.braintree');
    });

    Route::middleware('deny.impersonating')->group(function (): void {
        Route::post('subscribe', [SubscriptionController::class, 'store'])->name('subscribe');
        Route::match(['put', 'patch'], 'subscription/{subscription}', [SubscriptionController::class, 'update'])->name('swap');
        Route::post('subscription/{subscription}/resume', [SubscriptionController::class, 'resume'])->name('resume');

        Route::delete('subscription/{subscription}', [SubscriptionController::class, 'destroy'])
            ->middleware('password.confirm')
            ->name('cancel');
    });

    // -- Invoices ------------------------------------------------------------
    Route::prefix('invoices')->name('invoices.')->group(function (): void {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('{invoice}', [InvoiceController::class, 'show'])->name('show');
        Route::get('{invoice}/download', [InvoiceController::class, 'download'])->name('download');
    });

    // -- Payment methods -----------------------------------------------------
    Route::prefix('payment-methods')->name('payment-methods.')->group(function (): void {
        Route::get('/', [PaymentMethodController::class, 'index'])->name('index');
        Route::patch('{paymentMethod}/default', [PaymentMethodController::class, 'makeDefault'])->name('default');

        Route::middleware(['password.confirm', 'deny.impersonating'])->group(function (): void {
            Route::post('/', [PaymentMethodController::class, 'store'])->name('store');
            Route::delete('{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('destroy');
        });
    });

    // The plan and coupon catalogue prices the product rather than serving one
    // workspace, so it lives in the platform panel — see
    // app/Modules/Platform/Routes/web.php. The controllers stay here.
});
