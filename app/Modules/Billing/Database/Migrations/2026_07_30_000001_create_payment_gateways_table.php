<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which payment processors this installation accepts, and how to reach them.
 *
 * A table rather than settings keys: enabled, ordering, currency and country
 * rules are structured per driver, and ten processors with three or four
 * credentials each would otherwise mean sixty flat declarations in
 * SettingsSchema.
 *
 * Not company-scoped. Which processors the product takes money through is the
 * operator's decision, not a workspace's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();

            // Matches a key in GatewayRegistry; unique so a driver cannot be
            // configured twice and leave the checkout picker ambiguous.
            $table->string('driver', 64)->unique();
            $table->string('label');

            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_test_mode')->default(true);
            $table->unsignedInteger('sort')->default(0);

            // Encrypted as a whole rather than column-per-credential: the shape
            // differs per processor, and a blob keeps a new driver's fields from
            // needing a migration.
            $table->text('credentials')->nullable();

            // Empty means "no restriction". A processor that settles only in
            // EUR is not offered for a USD plan.
            $table->json('currencies')->nullable();
            $table->json('countries')->nullable();

            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_error', 500)->nullable();

            $table->timestamps();

            $table->index(['is_enabled', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
