<?php

declare(strict_types=1);

use App\Modules\Billing\Services\GatewayRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lists the Bangladeshi processors on installations that already exist.
 *
 * PaymentGatewaySeeder plants a row per registry driver, but it only runs on a
 * fresh install. Without this, an existing installation would upgrade to code
 * that knows about SSLCommerz, bKash, Nagad and aamarPay while the console —
 * which lists rows, not drivers — showed none of them.
 *
 * Each lands disabled and in test mode, with no credentials: an upgrade must
 * never start routing money somewhere new on its own.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const DRIVERS = ['sslcommerz', 'bkash', 'nagad', 'aamarpay'];

    public function up(): void
    {
        $registry = app(GatewayRegistry::class);
        $sort = (int) DB::table('payment_gateways')->max('sort');
        $now = now();

        foreach (self::DRIVERS as $driver) {
            $definition = $registry->get($driver);

            if ($definition === null) {
                continue;
            }

            // Insert-or-ignore rather than upsert, against the unique `driver`
            // index: on an installation where the seeder already planted these,
            // an upsert would reset `is_enabled` and wipe out an operator's own
            // currency and country rules.
            DB::table('payment_gateways')->insertOrIgnore([
                'driver' => $driver,
                'label' => $definition['label'],
                'is_enabled' => false,
                'is_test_mode' => true,
                'sort' => ++$sort,
                'currencies' => json_encode($definition['currencies']),
                'countries' => json_encode(['BD']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Only rows nobody has configured are removed.
     *
     * A rollback is a code-level operation; destroying an operator's stored
     * processor credentials because they rolled back a deploy would not be
     * recoverable from the migration.
     */
    public function down(): void
    {
        DB::table('payment_gateways')
            ->whereIn('driver', self::DRIVERS)
            ->where('is_enabled', false)
            ->where(function ($query): void {
                $query->whereNull('credentials')->orWhere('credentials', '');
            })
            ->delete();
    }
};
