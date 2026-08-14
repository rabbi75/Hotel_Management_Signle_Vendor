<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catalogue: what the vendor sells, and the discounts it honours.
 *
 * Deliberately not tenant-owned — plans and coupons belong to the operator of
 * the kit, not to any one workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Marketing bullet points, and the machine-readable ceilings that
            // SubscriptionLimits enforces. Two shapes, two columns.
            $table->json('features');
            $table->json('limits');

            // Integer minor units. Never a decimal, never a float.
            $table->unsignedInteger('monthly_price')->default(0);
            $table->unsignedInteger('yearly_price')->default(0);
            $table->char('currency', 3)->default('USD');

            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort')->default(0);

            // Gateway price identifiers, keyed by gateway then interval, so a
            // plan can be sold through more than one processor at once.
            $table->json('gateway_prices')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort']);
        });

        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('description')->nullable();
            $table->string('type', 16);

            // Percent (1-100) or an amount in minor units, per `type`.
            $table->unsignedInteger('value');
            $table->char('currency', 3)->nullable();

            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('expires_at')->nullable();

            // Null means "any plan"; otherwise a list of plan ids.
            $table->json('plan_ids')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('plans');
    }
};
