<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * An amount of money, held as integer minor units.
 *
 * Every arithmetic path below is integer-only. A float would accumulate
 * representation error across proration and percentage discounts, and the
 * cent that goes missing is the one a customer notices.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Money implements Arrayable, JsonSerializable
{
    /**
     * Minor-unit exponent. Zero- and three-decimal currencies exist, but the
     * kit's schema stores cents; a customer needing JPY or KWD changes this map
     * and the column semantics together.
     */
    private const SCALE = 100;

    /** @var array<string, string> */
    private const SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'INR' => '₹',
        'BDT' => '৳',
    ];

    private function __construct(
        public int $amount,
        public string $currency,
    ) {}

    public function __toString(): string
    {
        return $this->format();
    }

    public static function of(int $minorUnits, ?string $currency = null): self
    {
        return new self($minorUnits, self::normaliseCurrency($currency));
    }

    public static function zero(?string $currency = null): self
    {
        return new self(0, self::normaliseCurrency($currency));
    }

    /**
     * Parse a human-entered amount such as "19.99" without ever touching a float.
     */
    public static function fromMajor(string $major, ?string $currency = null): self
    {
        $trimmed = trim($major);

        if (! preg_match('/^(-?)(\d+)(?:[.,](\d{1,2}))?$/', $trimmed, $matches)) {
            throw new InvalidArgumentException("Unparseable money amount [{$major}].");
        }

        $units = (int) $matches[2];
        $fraction = (int) str_pad($matches[3] ?? '0', 2, '0', STR_PAD_RIGHT);
        $total = $units * self::SCALE + $fraction;

        return new self($matches[1] === '-' ? -$total : $total, self::normaliseCurrency($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multipliedBy(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    /**
     * A whole-percent share, rounded half away from zero — integer only.
     */
    public function percentage(int $percent): self
    {
        return new self(self::divideRounded($this->amount * $percent, 100), $this->currency);
    }

    /**
     * The share of this amount covered by `$numerator/$denominator` of a period.
     * Used for proration when a plan is swapped mid-cycle.
     */
    public function prorate(int $numerator, int $denominator): self
    {
        if ($denominator === 0) {
            return self::zero($this->currency);
        }

        return new self(self::divideRounded($this->amount * $numerator, $denominator), $this->currency);
    }

    /**
     * Clamp at zero: a discount larger than the amount must not invert the sign.
     */
    public function atLeastZero(): self
    {
        return $this->amount < 0 ? self::zero($this->currency) : $this;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    /**
     * The decimal representation, as a string so no caller can round-trip it
     * through a float by accident.
     */
    public function toMajor(): string
    {
        $sign = $this->amount < 0 ? '-' : '';
        $absolute = abs($this->amount);

        return $sign.intdiv($absolute, self::SCALE).'.'.str_pad((string) ($absolute % self::SCALE), 2, '0', STR_PAD_LEFT);
    }

    public function format(): string
    {
        $sign = $this->amount < 0 ? '-' : '';
        $absolute = abs($this->amount);
        $symbol = self::SYMBOLS[$this->currency] ?? $this->currency.' ';

        return $sign.$symbol.number_format((float) intdiv($absolute, self::SCALE), 0)
            .'.'.str_pad((string) ($absolute % self::SCALE), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{amount: int, currency: string, formatted: string}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    /**
     * @return array{amount: int, currency: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function normaliseCurrency(?string $currency): string
    {
        return strtoupper($currency ?? (string) config('saas.billing.currency', 'USD'));
    }

    /**
     * Integer division rounded half away from zero.
     */
    private static function divideRounded(int $numerator, int $denominator): int
    {
        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if (abs($remainder) * 2 >= abs($denominator)) {
            $quotient += ($numerator < 0) === ($denominator < 0) ? 1 : -1;
        }

        return $quotient;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot combine {$this->currency} with {$other->currency}.");
        }
    }
}
