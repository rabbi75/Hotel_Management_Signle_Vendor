<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Gateways\GatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * The landing spot for processors that send the customer back by POST.
 *
 * SSLCommerz and aamarPay submit a form to the success URL rather than issuing
 * a redirect. That arrives as a cross-site POST, and a `SameSite=lax` session
 * cookie is not sent on one — so {@see CheckoutController::return} would find
 * no current workspace and tell a customer who has just paid that their
 * checkout is no longer valid.
 *
 * Declared on the API group, which carries no session and therefore no CSRF
 * token check, for the same reason the webhook route is: a processor cannot
 * present a token. Nothing is granted here. All this does is read the
 * processor's reference out of the POST body and bounce the browser to the
 * ordinary signed return URL — a top-level GET navigation, which *does* carry
 * the session — where the existing verification runs unchanged.
 *
 * The `return` parameter is attacker-reachable, so it is validated as one of
 * our own signed checkout URLs before being redirected to. Without that check
 * this endpoint is an open redirect wearing a billing route's name.
 */
class CheckoutCallbackController extends Controller
{
    public function __construct(protected GatewayManager $gateways) {}

    public function __invoke(Request $request, string $gateway): RedirectResponse
    {
        $target = $this->verifiedReturnUrl($request);

        if ($target === null) {
            return redirect()->route('billing.plans')
                ->with('error', __('That checkout is no longer valid.'));
        }

        try {
            $reference = $this->gateways->driver($gateway)->referenceFromCallback($request);
        } catch (BillingException) {
            // An unconfigured or unknown driver must not be echoed back to the
            // caller; the customer is sent on to verify with what we have.
            $reference = null;
        }

        // Appended rather than merged into the signature: `ref` is one of the
        // parameters the return route is told to ignore, precisely so a
        // processor's own reference can ride along without invalidating a
        // signature computed over the parameters that decide what is granted.
        return redirect()->away(
            $reference === null || $reference === ''
                ? $target
                : $target.(str_contains($target, '?') ? '&' : '?').'ref='.urlencode($reference),
        );
    }

    /**
     * The `return` parameter, but only if it is genuinely a signed URL this
     * application issued for the checkout return route.
     *
     * Three things are checked, and all three matter: that the URL is ours by
     * host, that it resolves to `billing.checkout.return` specifically, and
     * that its signature is intact. Dropping any one of them turns this into a
     * redirector an attacker can point wherever they like.
     */
    protected function verifiedReturnUrl(Request $request): ?string
    {
        $target = $request->query('return');

        if (! is_string($target) || $target === '') {
            return null;
        }

        $parts = parse_url($target);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $expectedHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($expectedHost) || strcasecmp($parts['host'], $expectedHost) !== 0) {
            return null;
        }

        $probe = Request::create($target, 'GET');

        try {
            $route = Route::getRoutes()->match($probe);
        } catch (Throwable) {
            // No such route, or one that does not accept GET.
            return null;
        }

        if ($route->getName() !== 'billing.checkout.return') {
            return null;
        }

        // Validated under the same ignore list the return route itself uses, so
        // a URL accepted here is exactly one that route would accept.
        return URL::hasValidSignature($probe, true, CheckoutController::CALLBACK_PARAMETERS)
            ? $target
            : null;
    }
}
