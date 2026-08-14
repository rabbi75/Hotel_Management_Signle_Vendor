<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('guest_folio_id')->nullable()->constrained('guest_folios')->nullOnDelete();
            $table->string('number');
            $table->string('status', 32)->default('draft');
            $table->unsignedBigInteger('total')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_orders');
    }
};
