<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

/**
 * Everything an administrator may set on a plan.
 *
 * Prices arrive from the form in major units ("19.99") and are stored as
 * integer minor units; the conversion happens once, here, so no controller or
 * view ever has to remember which side of the boundary it is on.
 */
readonly class PlanData extends Data
{
    /**
     * Every field a request may carry, in the order they are written.
     */
    public const FIELDS = [
        'name',
        'slug',
        'description',
        'features',
        'entitlements',
        'limits',
        'monthly_price',
        'yearly_price',
        'currency',
        'trial_days',
        'is_active',
        'is_public',
        'sort',
        'gateway_prices',
    ];

    /**
     * @param  list<string>  $features
     * @param  list<string>  $entitlements
     * @param  array<string, int>  $limits
     * @param  array<string, array<string, string>>|null  $gatewayPrices
     * @param  list<string>  $provided  Request keys actually submitted. An absent
     *                                  key means "leave unchanged"; a key sent as
     *                                  null means "clear".
     */
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $description = null,
        public array $features = [],
        public array $entitlements = [],
        public array $limits = [],
        public int $monthlyPrice = 0,
        public int $yearlyPrice = 0,
        public string $currency = 'USD',
        public int $trialDays = 0,
        public bool $isActive = true,
        public bool $isPublic = true,
        public int $sort = 0,
        public ?array $gatewayPrices = null,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(
            self::FIELDS,
            static fn (string $field): bool => $request->has($field),
        ));

        /** @var list<string> $features */
        $features = array_values(array_filter(
            array_map(strval(...), (array) $request->input('features', [])),
            static fn (string $feature): bool => trim($feature) !== '',
        ));

        // Only keys declared in config/entitlements.php survive, so an unknown
        // or stale flag can never be persisted onto a plan.
        $allowed = array_keys((array) config('entitlements.features', []));

        /** @var list<string> $entitlements */
        $entitlements = array_values(array_intersect(
            $allowed,
            array_map(strval(...), (array) $request->input('entitlements', [])),
        ));

        /** @var array<string, int> $limits */
        $limits = [];

        foreach ((array) $request->input('limits', []) as $key => $value) {
            if (is_numeric($value)) {
                $limits[(string) $key] = (int) $value;
            }
        }

        $gatewayPrices = $request->input('gateway_prices');

        return new self(
            name: (string) $request->string('name'),
            slug: $request->string('slug')->toString() ?: null,
            description: $request->string('description')->toString() ?: null,
            features: $features,
            entitlements: $entitlements,
            limits: $limits,
            monthlyPrice: self::minorUnits($request->input('monthly_price')),
            yearlyPrice: self::minorUnits($request->input('yearly_price')),
            currency: strtoupper($request->string('currency', (string) config('saas.billing.currency'))->toString()),
            trialDays: (int) $request->integer('trial_days'),
            isActive: $request->boolean('is_active', true),
            isPublic: $request->boolean('is_public', true),
            sort: (int) $request->integer('sort'),
            gatewayPrices: is_array($gatewayPrices) ? self::normaliseGatewayPrices($gatewayPrices) : null,
            provided: $provided,
        );
    }

    public function wasProvided(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'features' => $this->features,
            'entitlements' => $this->entitlements,
            'limits' => $this->limits,
            'monthly_price' => $this->monthlyPrice,
            'yearly_price' => $this->yearlyPrice,
            'currency' => $this->currency,
            'trial_days' => $this->trialDays,
            'is_active' => $this->isActive,
            'is_public' => $this->isPublic,
            'sort' => $this->sort,
            'gateway_prices' => $this->gatewayPrices,
        ];
    }

    /**
     * Only the fields the request actually carried.
     *
     * Without this, an edit form that does not render the gateway price matrix
     * would wipe every Stripe price id on save — an omission is not a clear.
     *
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(): array
    {
        return array_intersect_key($this->toAttributes(), array_flip($this->provided));
    }

    /**
     * Accepts "19.99", "19", 1999 (already minor) is *not* assumed — the form
     * always posts major units, so the conversion is unconditional.
     */
    protected static function minorUnits(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $normalised = str_replace(',', '.', (string) $value);

        // Prices are stored in an unsigned column; a negative here is bad input,
        // not a credit, and the FormRequest has already rejected it.
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $normalised)) {
            return 0;
        }

        [$units, $fraction] = array_pad(explode('.', $normalised, 2), 2, '0');

        return (int) $units * 100 + (int) str_pad($fraction, 2, '0', STR_PAD_RIGHT);
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return array<string, array<string, string>>
     */
    protected static function normaliseGatewayPrices(array $input): array
    {
        $result = [];

        foreach ($input as $gateway => $intervals) {
            if (! is_array($intervals)) {
                continue;
            }

            foreach ($intervals as $interval => $priceId) {
                if (is_string($priceId) && $priceId !== '') {
                    $result[(string) $gateway][(string) $interval] = $priceId;
                }
            }
        }

        return $result;
    }
}
