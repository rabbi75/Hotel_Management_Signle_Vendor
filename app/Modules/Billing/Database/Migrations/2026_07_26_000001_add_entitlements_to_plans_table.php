<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Machine-readable feature flags for a plan.
 *
 * Distinct from the existing `features` column, which is free-text marketing
 * copy. `entitlements` is a list of keys from config/entitlements.php that
 * middleware and the navigation gate check — copy a workspace's plan grants,
 * not a sentence a human wrote.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL rejects a DEFAULT on a JSON column, so the column is added
        // nullable, backfilled to an empty array, then made non-null.
        Schema::table('plans', function (Blueprint $table): void {
            $table->json('entitlements')->nullable()->after('features');
        });

        DB::table('plans')->whereNull('entitlements')->update(['entitlements' => '[]']);

        Schema::table('plans', function (Blueprint $table): void {
            $table->json('entitlements')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('entitlements');
        });
    }
};
