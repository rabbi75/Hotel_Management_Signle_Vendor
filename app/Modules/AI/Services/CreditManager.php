<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use App\Modules\AI\Enums\CreditTransactionType;
use App\Modules\AI\Exceptions\InsufficientCreditsException;
use App\Modules\AI\Models\AiCreditBalance;
use App\Modules\AI\Models\AiCreditTransaction;
use App\Modules\Billing\Services\SubscriptionLimits;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Monthly AI credit accounting.
 *
 * Reserve-then-settle: credits are held before the provider is called and only
 * converted into a charge once the call returns, so a failed generation costs
 * nothing. Every mutation is a single conditional UPDATE — a read-then-write
 * would let two concurrent generations both see the same balance and both
 * succeed, overdrawing the workspace.
 */
class CreditManager
{
    public function balance(?int $companyId = null, ?string $period = null): AiCreditBalance
    {
        $companyId = $companyId ?? (int) current_company_id();
        $period ??= AiCreditBalance::currentPeriod();

        $balance = $this->query()
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->first();

        if ($balance instanceof AiCreditBalance) {
            return $balance;
        }

        $balance = new AiCreditBalance([
            'company_id' => $companyId,
            'period' => $period,
            'allowance' => $this->monthlyAllowance($companyId),
            'used' => 0,
            'reserved' => 0,
        ]);

        $balance->save();

        return $balance;
    }

    /**
     * Hold `$credits` for an in-flight generation.
     *
     * @throws InsufficientCreditsException
     */
    public function reserve(int $credits, ?int $companyId = null, ?int $userId = null): AiCreditTransaction
    {
        $credits = max(1, $credits);
        $companyId = $companyId ?? (int) current_company_id();
        $balance = $this->balance($companyId);

        if (! $this->creditsEnabled()) {
            return $this->record($companyId, $userId, $balance->period, CreditTransactionType::Reservation, 0, __('Credits disabled.'));
        }

        $affected = $this->query()
            ->whereKey($balance->id)
            ->whereRaw('allowance - used - reserved >= ?', [$credits])
            ->update(['reserved' => new Expression("reserved + {$credits}")]);

        if ($affected === 0) {
            throw InsufficientCreditsException::for($credits, $balance->fresh()?->available() ?? 0);
        }

        return $this->record($companyId, $userId, $balance->period, CreditTransactionType::Reservation, $credits, __('Reserved for a generation.'));
    }

    /**
     * Convert a reservation into an actual charge.
     */
    public function settle(AiCreditTransaction $reservation, int $actualCredits, ?int $generationId = null): AiCreditTransaction
    {
        $actualCredits = max(0, $actualCredits);
        $balance = $this->balance($reservation->company_id, $reservation->period);

        $this->query()
            ->whereKey($balance->id)
            ->update([
                'reserved' => new Expression($this->clampedSubtraction('reserved', $reservation->credits)),
                'used' => new Expression("used + {$actualCredits}"),
            ]);

        return $this->record(
            $reservation->company_id,
            $reservation->user_id,
            $reservation->period,
            CreditTransactionType::Charge,
            $actualCredits,
            __('Generation charged.'),
            $generationId,
        );
    }

    /**
     * Give a reservation back untouched — the provider call never produced
     * billable output.
     */
    public function release(AiCreditTransaction $reservation): AiCreditTransaction
    {
        $balance = $this->balance($reservation->company_id, $reservation->period);

        $this->query()
            ->whereKey($balance->id)
            ->update(['reserved' => new Expression($this->clampedSubtraction('reserved', $reservation->credits))]);

        return $this->record(
            $reservation->company_id,
            $reservation->user_id,
            $reservation->period,
            CreditTransactionType::Refund,
            $reservation->credits,
            __('Reservation released after a failed generation.'),
        );
    }

    /**
     * An administrator raising or lowering this month's allowance.
     */
    public function adjust(int $companyId, int $credits, ?int $userId, string $reason): AiCreditTransaction
    {
        $balance = $this->balance($companyId);

        $this->query()
            ->whereKey($balance->id)
            ->update(['allowance' => new Expression($this->clampedAddition('allowance', $credits))]);

        return $this->record($companyId, $userId, $balance->period, CreditTransactionType::Adjustment, $credits, $reason);
    }

    /**
     * The credit allowance for one workspace's billing period.
     *
     * The subscribed plan's `ai_credits` limit wins when billing is on and the
     * plan sets one; otherwise the flat config allowance applies, so a kit with
     * no billing still hands out credits.
     */
    protected function monthlyAllowance(int $companyId): int
    {
        $limit = app(SubscriptionLimits::class)->limit('ai_credits', Company::query()->find($companyId));

        if ($limit >= 0) {
            return $limit;
        }

        $configured = setting('ai.monthly_credits');

        return is_numeric($configured)
            ? (int) $configured
            : (int) config('saas.ai.credits.monthly_allowance');
    }

    protected function creditsEnabled(): bool
    {
        $configured = setting('ai.credits_enabled');

        if ($configured !== null) {
            return (bool) $configured;
        }

        return (bool) config('saas.ai.credits.enabled');
    }

    /**
     * `GREATEST` is MySQL-only, and these columns are unsigned: a subtraction
     * that would go negative has to be clamped in SQL, inside the same atomic
     * statement, or the race safety is lost.
     */
    protected function clampedSubtraction(string $column, int $amount): string
    {
        return "CASE WHEN {$column} > {$amount} THEN {$column} - {$amount} ELSE 0 END";
    }

    protected function clampedAddition(string $column, int $amount): string
    {
        return $amount >= 0
            ? "{$column} + {$amount}"
            : $this->clampedSubtraction($column, abs($amount));
    }

    /**
     * @return Builder<AiCreditBalance>
     */
    protected function query()
    {
        // The scope would be a no-op in a queued job and actively wrong for an
        // administrator adjusting another workspace, so scoping is explicit.
        return AiCreditBalance::query()->withoutGlobalScope(CompanyScope::class);
    }

    protected function record(
        int $companyId,
        ?int $userId,
        string $period,
        CreditTransactionType $type,
        int $credits,
        string $description,
        ?int $generationId = null,
    ): AiCreditTransaction {
        return DB::transaction(function () use ($companyId, $userId, $period, $type, $credits, $description, $generationId): AiCreditTransaction {
            $transaction = new AiCreditTransaction([
                'company_id' => $companyId,
                'user_id' => $userId,
                'generation_id' => $generationId,
                'period' => $period,
                'type' => $type,
                'credits' => $credits,
                'description' => $description,
            ]);

            $transaction->save();

            return $transaction;
        });
    }
}
