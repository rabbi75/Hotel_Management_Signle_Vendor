<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            $table->string('number')->unique();
            $table->string('status', 24);

            // All integer minor units.
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('tax')->default(0);
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->char('currency', 3);

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->string('gateway', 32)->nullable();
            $table->string('gateway_id')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'issued_at']);
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->integer('unit_amount')->default(0);
            $table->integer('amount')->default(0);
            $table->string('period')->nullable();
            $table->timestamps();

            $table->index('invoice_id', 'invoice_lines_invoice_idx');
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->string('gateway', 32);
            $table->string('gateway_id')->nullable();
            $table->string('type', 16);
            $table->string('status', 24);

            $table->integer('amount');
            $table->char('currency', 3);

            $table->timestamp('processed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 32);
            $table->string('gateway_id');
            $table->string('type', 32)->default('card');
            $table->string('brand', 32)->nullable();
            $table->string('last_four', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->string('holder_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['gateway', 'gateway_id']);
            $table->index(['company_id', 'is_default']);
        });

        Schema::create('billing_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 32);

            // The gateway's own event id. Unique per gateway, which is what
            // makes webhook handling idempotent under redelivery.
            $table->string('event_id');
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
