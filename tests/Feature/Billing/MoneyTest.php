<?php

declare(strict_types=1);

use App\Modules\Billing\Support\Money;

/*
|------------------------------------------------------------------------------
| Money is integer-only, and must stay that way.
|------------------------------------------------------------------------------
|
| The cases below are the ones a float representation gets wrong: repeated
| accumulation of 0.10, a percentage that lands exactly on a half cent, and
| proration across a period that does not divide evenly.
|
*/

it('accumulates without drift', function (): void {
    $total = Money::zero('USD');

    for ($i = 0; $i < 1000; $i++) {
        $total = $total->plus(Money::of(10, 'USD'));
    }

    expect($total->amount)->toBe(10000)
        ->and($total->toMajor())->toBe('100.00');
});

it('parses major units without a float round trip', function (): void {
    expect(Money::fromMajor('19.99')->amount)->toBe(1999)
        ->and(Money::fromMajor('0.07')->amount)->toBe(7)
        ->and(Money::fromMajor('1234')->amount)->toBe(123400)
        ->and(Money::fromMajor('-5.05')->amount)->toBe(-505);
});

it('rejects an unparseable amount', function (): void {
    Money::fromMajor('nineteen ninety nine');
})->throws(InvalidArgumentException::class);

it('rounds a percentage half away from zero', function (): void {
    // 5% of 1_050 is exactly 52.5 minor units.
    expect(Money::of(1050, 'USD')->percentage(5)->amount)->toBe(53)
        ->and(Money::of(-1050, 'USD')->percentage(5)->amount)->toBe(-53)
        ->and(Money::of(3333, 'USD')->percentage(33)->amount)->toBe(1100);
});

it('prorates with integer arithmetic', function (): void {
    // A third of a month on a 10.00 plan.
    expect(Money::of(1000, 'USD')->prorate(10, 30)->amount)->toBe(333)
        ->and(Money::of(1000, 'USD')->prorate(0, 30)->amount)->toBe(0)
        ->and(Money::of(1000, 'USD')->prorate(30, 30)->amount)->toBe(1000)
        ->and(Money::of(1000, 'USD')->prorate(1, 0)->amount)->toBe(0);
});

it('never inverts sign when clamped', function (): void {
    expect(Money::of(500, 'USD')->minus(Money::of(900, 'USD'))->atLeastZero()->amount)->toBe(0);
});

it('refuses to mix currencies', function (): void {
    Money::of(100, 'USD')->plus(Money::of(100, 'EUR'));
})->throws(InvalidArgumentException::class);

it('formats with a thousands separator and two decimals', function (): void {
    expect(Money::of(123456789, 'USD')->format())->toBe('$1,234,567.89')
        ->and(Money::of(5, 'USD')->format())->toBe('$0.05')
        ->and(Money::of(-1250, 'GBP')->format())->toBe('-£12.50')
        ->and(Money::of(1000, 'XYZ')->format())->toBe('XYZ 10.00');
});
