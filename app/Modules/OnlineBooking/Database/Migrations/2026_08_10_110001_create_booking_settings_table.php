<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->unique()->constrained('hotels')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('public_slug')->nullable();
            $table->unsignedSmallInteger('min_advance_days')->default(0);
            $table->unsignedSmallInteger('max_advance_days')->default(365);
            $table->boolean('require_deposit')->default(false);
            $table->unsignedBigInteger('deposit_amount')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'public_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_settings');
    }
};
