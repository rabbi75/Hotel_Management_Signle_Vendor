<?php

declare(strict_types=1);

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Models\PaymentGatewayConfig;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\GatewayConfigRepository;
use App\Modules\Billing\Services\GatewayRegistry;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

/**
 * Resolves payment gateways, and decides which of them may be offered.
 *
 * Drivers are registered as factories rather than eagerly constructed, so a
 * gateway whose SDK needs credentials is never instantiated on a request that
 * does not touch billing.
 *
 * Which drivers *exist* is fixed in code; which are enabled, in what order, and
 * for which currencies is operator configuration read from the database. The
 * two are kept apart deliberately: an operator can turn a processor on, but not
 * invent one.
 */
class GatewayManager
{
    /** @var array<string, Closure(Container): PaymentGateway> */
    protected array $drivers = [];

    /** @var array<string, PaymentGateway> */
    protected array $resolved = [];

    public function __construct(
        protected Container $container,
        protected GatewayConfigRepository $configs,
        protected GatewayRegistry $registry,
    ) {
        $this->registerDefaults();
    }

    /**
     * @param  Closure(Container): PaymentGateway  $factory
     */
    public function extend(string $name, Closure $factory): void
    {
        $this->drivers[$name] = $factory;
        unset($this->resolved[$name]);
    }

    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= $this->defaultDriver();

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $factory = $this->drivers[$name] ?? null;

        if ($factory === null) {
            throw new BillingException("No payment gateway registered for driver [{$name}].");
        }

        return $this->resolved[$name] = $factory($this->container);
    }

    /**
     * The gateway a purchase uses when the customer was not offered a choice.
     *
     * The first enabled and fully configured processor wins, in the operator's
     * own order. Config is the fallback so an installation that has never
     * opened the gateways screen behaves exactly as it did before there was one.
     */
    public function defaultDriver(): string
    {
        $first = $this->configs->enabled($this->registry)->keys()->first();

        if (is_string($first)) {
            return $first;
        }

        $driver = config('saas.billing.gateway', 'manual');

        return is_string($driver) && $driver !== '' ? $driver : 'manual';
    }

    /**
     * The gateways that may take payment for one plan.
     *
     * Filtered by the plan's currency and, when known, the buyer's country: a
     * processor that cannot settle the amount must not be offered, because the
     * failure would otherwise land on the customer mid-checkout.
     *
     * @return Collection<string, PaymentGatewayConfig>
     */
    public function enabledFor(Plan $plan, ?string $country = null): Collection
    {
        return $this->configs
            ->enabled($this->registry)
            ->filter(fn (PaymentGatewayConfig $config): bool => $this->drivers[$config->driver] ?? null
                ? $config->supportsCurrency($plan->currency) && $config->supportsCountry($country)
                : false);
    }

    /**
     * Every driver the code knows how to construct.
     *
     * @return list<string>
     */
    public function available(): array
    {
        return array_keys($this->drivers);
    }

    protected function registerDefaults(): void
    {
        $this->extend('manual', static fn (Container $app): PaymentGateway => $app->make(ManualGateway::class));
        $this->extend('stripe', static fn (Container $app): PaymentGateway => $app->make(StripeGateway::class));
        $this->extend('paypal', static fn (Container $app): PaymentGateway => $app->make(PayPalGateway::class));
        $this->extend('paddle', static fn (Container $app): PaymentGateway => $app->make(PaddleGateway::class));
        $this->extend('lemonsqueezy', static fn (Container $app): PaymentGateway => $app->make(LemonSqueezyGateway::class));
        $this->extend('braintree', static fn (Container $app): PaymentGateway => $app->make(BraintreeGateway::class));
        $this->extend('square', static fn (Container $app): PaymentGateway => $app->make(SquareGateway::class));
        $this->extend('mollie', static fn (Container $app): PaymentGateway => $app->make(MollieGateway::class));
        $this->extend('razorpay', static fn (Container $app): PaymentGateway => $app->make(RazorpayGateway::class));
        $this->extend('paystack', static fn (Container $app): PaymentGateway => $app->make(PaystackGateway::class));
        $this->extend('flutterwave', static fn (Container $app): PaymentGateway => $app->make(FlutterwaveGateway::class));

        // Bangladesh. SSLCommerz first: it is the aggregator that reaches every
        // wallet at once, so an operator enabling one processor should land on
        // it before the direct-wallet drivers below.
        $this->extend('sslcommerz', static fn (Container $app): PaymentGateway => $app->make(SslCommerzGateway::class));
        $this->extend('bkash', static fn (Container $app): PaymentGateway => $app->make(BkashGateway::class));
        $this->extend('nagad', static fn (Container $app): PaymentGateway => $app->make(NagadGateway::class));
        $this->extend('aamarpay', static fn (Container $app): PaymentGateway => $app->make(AamarPayGateway::class));
    }
}
