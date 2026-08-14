<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();

            // Which driver owns the remote record, and its id there. `manual`
            // leaves gateway_id null because nothing external exists.
            $table->string('gateway', 32);
            $table->string('gateway_id')->nullable();

            $table->string('status', 24);
            $table->string('interval', 16);
            $table->unsignedInteger('quantity')->default(1);

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();

            // Set when the customer cancels at period end; the subscription is
            // still `active` until the renewal command sweeps it.
            $table->timestamp('cancels_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            // Consecutive failed renewal attempts, for dunning.
            $table->unsignedTinyInteger('dunning_attempts')->default(0);
            $table->timestamp('past_due_since')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'current_period_end']);
            $table->unique(['gateway', 'gateway_id']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_off')->default(0);
            $table->char('currency', 3);
            $table->timestamp('redeemed_at');
            $table->timestamps();

            // One redemption of a given code per workspace.
            $table->unique(['coupon_id', 'company_id']);
        });

        Schema::create('usage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metric', 64);
            $table->integer('quantity');
            $table->timestamp('recorded_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'metric', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('subscriptions');
    }
};
