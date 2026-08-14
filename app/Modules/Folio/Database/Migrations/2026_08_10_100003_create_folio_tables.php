<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained('hotels')->nullOnDelete();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->string('category', 32)->default('other');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedSmallInteger('tax_rate')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'hotel_id']);
            $table->unique(['company_id', 'code']);
        });

        Schema::create('guest_folios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->string('number', 32)->unique();
            $table->string('status', 24)->default('open');
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('tax')->default(0);
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('paid_amount')->default(0);
            $table->unsignedInteger('balance')->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('reservation_id');
            $table->index(['company_id', 'hotel_id', 'status']);
        });

        Schema::create('folio_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_folio_id')->constrained('guest_folios')->cascadeOnDelete();
            $table->foreignId('hotel_service_id')->nullable()->constrained('hotel_services')->nullOnDelete();
            $table->string('type', 24);
            $table->string('description');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->integer('unit_price')->default(0);
            $table->integer('amount');
            $table->unsignedInteger('tax_amount')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['guest_folio_id', 'type']);
        });

        Schema::create('guest_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_folio_id')->constrained('guest_folios')->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->char('currency', 3)->default('USD');
            $table->string('method', 24);
            $table->string('status', 24)->default('completed');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'guest_folio_id']);
        });

        Schema::create('guest_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_folio_id')->constrained('guest_folios')->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->string('number', 32)->unique();
            $table->string('status', 24)->default('issued');
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('tax')->default(0);
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('guest_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_invoice_id')->constrained('guest_invoices')->cascadeOnDelete();
            $table->string('description');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->integer('unit_price')->default(0);
            $table->integer('amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_invoice_lines');
        Schema::dropIfExists('guest_invoices');
        Schema::dropIfExists('guest_payments');
        Schema::dropIfExists('folio_items');
        Schema::dropIfExists('guest_folios');
        Schema::dropIfExists('hotel_services');
    }
};
