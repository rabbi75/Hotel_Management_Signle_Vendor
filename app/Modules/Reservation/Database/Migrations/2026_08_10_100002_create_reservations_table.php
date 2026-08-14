<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('number');
            $table->foreignId('guest_id')->constrained('guests')->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->foreignId('room_type_id')->nullable()->constrained('room_types')->nullOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('rooms_count')->default(1);
            $table->string('booking_source', 64)->default('walk_in');
            $table->text('special_requests')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedBigInteger('due_amount')->default(0);
            $table->string('status', 32)->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'hotel_id', 'status']);
            $table->index(['guest_id']);
            $table->index(['room_id', 'check_in_date', 'check_out_date']);
            $table->index(['bed_id', 'check_in_date', 'check_out_date']);
            $table->index(['check_in_date', 'check_out_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
