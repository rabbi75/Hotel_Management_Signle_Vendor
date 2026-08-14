<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->string('number', 32)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 24)->default('other');
            $table->string('priority', 24)->default('normal');
            $table->string('status', 24)->default('open');
            $table->boolean('blocks_room')->default(true);
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'hotel_id', 'status']);
            $table->index(['room_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
