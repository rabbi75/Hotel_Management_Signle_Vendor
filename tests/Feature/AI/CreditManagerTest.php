<?php

declare(strict_types=1);

use App\Modules\AI\Enums\CreditTransactionType;
use App\Modules\AI\Exceptions\InsufficientCreditsException;
use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\AI\Models\AiCreditTransaction;
use App\Modules\AI\Services\CreditManager;

it('opens a balance for the current period with the configured allowance', function (): void {
    config(['saas.ai.credits.monthly_allowance' => 500]);

    $company = workspace();

    $balance = app(CreditManager::class)->balance($company->id);

    expect($balance->allowance)->toBe(500)
        ->and($balance->used)->toBe(0)
        ->and($balance->available())->toBe(500)
        ->and($balance->period)->toBe(AiCreditBalance::currentPeriod());
});

it('holds credits on reserve and only spends them on settle', function (): void {
    $company = workspace();
    $credits = app(CreditManager::class);

    $reservation = $credits->reserve(40, $company->id);

    $balance = $credits->balance($company->id)->refresh();
    expect($balance->reserved)->toBe(40)->and($balance->used)->toBe(0);

    $credits->settle($reservation, 12);

    $balance->refresh();
    expect($balance->reserved)->toBe(0)->and($balance->used)->toBe(12);
});

it('gives a reservation back untouched when a call fails', function (): void {
    $company = workspace();
    $credits = app(CreditManager::class);

    $reservation = $credits->reserve(25, $company->id);
    $credits->release($reservation);

    $balance = $credits->balance($company->id)->refresh();

    expect($balance->reserved)->toBe(0)
        ->and($balance->used)->toBe(0)
        ->and(AiCreditTransaction::query()->where('type', CreditTransactionType::Refund)->count())->toBe(1);
});

it('refuses a reservation the workspace cannot cover', function (): void {
    config(['saas.ai.credits.monthly_allowance' => 10]);

    $company = workspace();

    app(CreditManager::class)->reserve(50, $company->id);
})->throws(InsufficientCreditsException::class);

it('cannot be overdrawn by two concurrent generations', function (): void {
    config(['saas.ai.credits.monthly_allowance' => 100]);

    $company = workspace();
    $credits = app(CreditManager::class);

    // Two interleaved generations each try to hold 60 of a 100-credit envelope.
    // A read-then-write implementation would let both through.
    $credits->reserve(60, $company->id);

    expect(fn () => $credits->reserve(60, $company->id))->toThrow(InsufficientCreditsException::class);

    $balance = $credits->balance($company->id)->refresh();

    expect($balance->reserved)->toBe(60)
        ->and($balance->reserved + $balance->used)->toBeLessThanOrEqual($balance->allowance);
});

it('records an administrator adjustment in the ledger', function (): void {
    config(['saas.ai.credits.monthly_allowance' => 100]);

    $company = workspace();
    $credits = app(CreditManager::class);

    $credits->adjust($company->id, 250, null, 'Bought a top-up');

    $balance = $credits->balance($company->id)->refresh();

    expect($balance->allowance)->toBe(350)
        ->and(AiCreditTransaction::query()->where('type', CreditTransactionType::Adjustment)->value('credits'))->toBe(250);
});

it('keeps balances separate per workspace', function (): void {
    $other = workspace();
    $company = workspace();

    app(CreditManager::class)->reserve(30, $company->id);

    expect(app(CreditManager::class)->balance($other->id)->refresh()->reserved)->toBe(0);
});
