<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\UsageRecord;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;

/**
 * Append one consumption event to the usage ledger.
 *
 * Other modules call this — the AI module for credits, Media for storage — so
 * it deliberately accepts a bare metric name rather than an enum the caller
 * would have to import.
 */
class RecordUsage
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function handle(
        Company $company,
        string $metric,
        int $quantity = 1,
        ?Subscription $subscription = null,
        ?array $meta = null,
    ): UsageRecord {
        $subscription ??= $this->currentSubscription($company);

        $record = new UsageRecord([
            'subscription_id' => $subscription?->id,
            'metric' => $metric,
            'quantity' => $quantity,
            'recorded_at' => CarbonImmutable::now(),
            'meta' => $meta,
        ]);

        $record->company_id = $company->id;
        $record->save();

        return $record;
    }

    protected function currentSubscription(Company $company): ?Subscription
    {
        return Subscription::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->live()
            ->latest('id')
            ->first();
    }
}
