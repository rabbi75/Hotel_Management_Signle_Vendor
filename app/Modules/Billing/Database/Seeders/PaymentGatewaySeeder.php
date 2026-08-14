<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Seeders;

use App\Modules\Billing\Models\PaymentGatewayConfig;
use App\Modules\Billing\Services\GatewayRegistry;
use Illuminate\Database\Seeder;

/**
 * Plants a row per known processor so the console lists all of them from a
 * fresh install, each disabled until an operator supplies credentials.
 *
 * Idempotent, and deliberately non-destructive: a re-run refreshes the label
 * and the suggested currencies but never touches `is_enabled`, `credentials`
 * or an operator's own currency and country rules.
 */
class PaymentGatewaySeeder extends Seeder
{
    public function __construct(protected GatewayRegistry $registry) {}

    public function run(): void
    {
        $sort = 0;

        foreach ($this->registry->all() as $driver => $definition) {
            $existing = PaymentGatewayConfig::query()->where('driver', $driver)->first();

            if ($existing instanceof PaymentGatewayConfig) {
                $existing->label = $definition['label'];
                $existing->save();

                $sort++;

                continue;
            }

            PaymentGatewayConfig::query()->create([
                'driver' => $driver,
                'label' => $definition['label'],

                // The offline driver is the one that works with no credentials,
                // so it is what a fresh installation can actually transact on.
                'is_enabled' => $driver === 'manual',
                'is_test_mode' => $driver !== 'manual',
                'sort' => $sort++,
                'credentials' => [],
                'currencies' => $definition['currencies'],
                'countries' => [],
            ]);
        }
    }
}
