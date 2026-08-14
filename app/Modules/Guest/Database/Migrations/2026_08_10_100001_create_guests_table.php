<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained('hotels')->nullOnDelete();
            $table->ulid('uuid')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender', 32)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('nationality')->nullable();
            $table->string('id_type', 64)->nullable();
            $table->string('id_number', 128)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'last_name', 'first_name']);
            $table->index(['company_id', 'email']);
            $table->index(['company_id', 'phone']);
            $table->index(['company_id', 'hotel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
