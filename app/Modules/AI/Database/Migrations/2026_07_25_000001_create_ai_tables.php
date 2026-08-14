<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->string('category', 60)->nullable();
            $table->text('prompt');

            // Declared `{{variable}}` placeholders and their input metadata, so
            // the playground can render a form instead of a raw text box.
            $table->json('variables');

            $table->string('provider', 40)->nullable();
            $table->string('model', 120)->nullable();
            $table->boolean('is_shared')->default(false);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'category']);
        });

        Schema::create('ai_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('ai_prompt_templates')->nullOnDelete();
            $table->string('provider', 40);
            $table->string('model', 120);
            $table->longText('input');
            $table->longText('output')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('credits_charged')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('stop_reason', 40)->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'provider']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('ai_credit_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // One row per workspace per calendar month: allowances reset, but
            // the history of what was spent must survive the reset.
            $table->string('period', 7);

            $table->unsignedInteger('allowance');
            $table->unsignedInteger('used')->default(0);

            // Credits held for in-flight generations. Reserved separately from
            // `used` so a failed call gives them back without ever having been
            // spent.
            $table->unsignedInteger('reserved')->default(0);

            $table->timestamps();

            $table->unique(['company_id', 'period']);
        });

        Schema::create('ai_credit_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('ai_generations')->nullOnDelete();
            $table->string('period', 7);
            $table->string('type', 20);
            $table->integer('credits');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credit_transactions');
        Schema::dropIfExists('ai_credit_balances');
        Schema::dropIfExists('ai_generations');
        Schema::dropIfExists('ai_prompt_templates');
    }
};
