<?php

declare(strict_types=1);

use App\Modules\Billing\Http\Controllers\CheckoutCallbackController;
use App\Modules\Billing\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Billing webhooks
|------------------------------------------------------------------------------
|
| Declared on the API group rather than the web group: a payment processor
| cannot present a CSRF token, and the API group carries no session and so no
| token check. Authenticity is established by the driver's own signature
| verification, and replay by the unique (gateway, event_id) index.
|
| Deliberately unauthenticated — the signature is the credential.
|
*/

Route::post('billing/webhook/{gateway?}', WebhookController::class)
    ->withoutMiddleware(['throttle:api'])
    ->name('billing.webhook');

/*
| The return leg for processors that send the customer back with a form POST
| rather than a redirect — SSLCommerz and aamarPay.
|
| Here for the same reason: a cross-site POST carries no CSRF token, and it also
| carries no SameSite=lax session cookie, so the ordinary return route would
| find no workspace. This one grants nothing; it validates the signed return URL
| it was handed and bounces the browser there as a GET, which does carry the
| session. See CheckoutCallbackController.
*/
Route::post('billing/checkout/callback/{gateway}', CheckoutCallbackController::class)
    ->withoutMiddleware(['throttle:api'])
    ->name('billing.checkout.callback');
