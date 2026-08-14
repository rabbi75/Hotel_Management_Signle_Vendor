<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_support_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->json('placeholders')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('platform_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('heading');
            $table->text('message');
            $table->string('level')->default('info');
            $table->string('audience')->default('all'); // all|active|suspended
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_announcements');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('company_support_notes');
    }
};
