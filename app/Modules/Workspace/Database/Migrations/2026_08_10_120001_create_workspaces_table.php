<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->ulid('uuid')->unique();
            $table->string('name');
            $table->string('slug');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->boolean('is_default')->default(false);
            $table->string('timezone')->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'is_default']);
        });

        Schema::create('workspace_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('member');
            $table->string('status', 32)->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
    }
};
