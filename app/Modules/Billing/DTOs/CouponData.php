<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\CouponType;
use App\Support\DTOs\Data;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

readonly class CouponData extends Data
{
    public const FIELDS = [
        'code',
        'description',
        'type',
        'value',
        'currency',
        'max_redemptions',
        'expires_at',
        'plan_ids',
        'is_active',
    ];

    /**
     * @param  list<int>|null  $planIds  Null means "every plan".
     * @param  list<string>  $provided
     */
    public function __construct(
        public string $code,
        public CouponType $type = CouponType::Percent,
        public int $value = 0,
        public ?string $description = null,
        public ?string $currency = null,
        public ?int $maxRedemptions = null,
        public ?CarbonImmutable $expiresAt = null,
        public ?array $planIds = null,
        public bool $isActive = true,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(
            self::FIELDS,
            static fn (string $field): bool => $request->has($field),
        ));

        $type = CouponType::tryFrom((string) $request->string('type')) ?? CouponType::Percent;
        $rawValue = $request->input('value');

        /** @var list<int>|null $planIds */
        $planIds = $request->has('plan_ids')
            ? array_values(array_map(intval(...), array_filter((array) $request->input('plan_ids', []), is_numeric(...))))
            : null;

        $expiresAt = $request->string('expires_at')->toString();

        return new self(
            code: strtoupper(trim((string) $request->string('code'))),
            type: $type,
            // A percent coupon's value is a whole percent; a fixed coupon's is
            // an amount, and the form posts it in major units.
            value: $type === CouponType::Percent
                ? (int) $request->integer('value')
                : self::minorUnits($rawValue),
            description: $request->string('description')->toString() ?: null,
            currency: $request->string('currency')->toString() ?: null,
            maxRedemptions: $request->filled('max_redemptions') ? (int) $request->integer('max_redemptions') : null,
            expiresAt: $expiresAt !== '' ? CarbonImmutable::parse($expiresAt) : null,
            planIds: $planIds === [] ? null : $planIds,
            isActive: $request->boolean('is_active', true),
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
            'code' => $this->code,
            'description' => $this->description,
            'type' => $this->type,
            'value' => $this->value,
            'currency' => $this->currency,
            'max_redemptions' => $this->maxRedemptions,
            'expires_at' => $this->expiresAt,
            'plan_ids' => $this->planIds,
            'is_active' => $this->isActive,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(): array
    {
        return array_intersect_key($this->toAttributes(), array_flip($this->provided));
    }

    protected static function minorUnits(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $normalised = str_replace(',', '.', (string) $value);

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $normalised)) {
            return 0;
        }

        [$units, $fraction] = array_pad(explode('.', $normalised, 2), 2, '0');

        return (int) $units * 100 + (int) str_pad($fraction, 2, '0', STR_PAD_RIGHT);
    }
}
