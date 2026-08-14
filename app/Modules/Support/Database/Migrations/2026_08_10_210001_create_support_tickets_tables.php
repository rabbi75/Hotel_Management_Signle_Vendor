<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('subject');
            $table->string('status')->default('awaiting_support');
            $table->string('priority')->default('normal');
            $table->string('category')->default('other');
            $table->timestamp('last_replied_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('assigned_admin_id');
        });

        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
