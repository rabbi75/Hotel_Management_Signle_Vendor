<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('seoable_type');
            $table->unsignedBigInteger('seoable_id');

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('keywords')->nullable();
            $table->string('canonical_url', 2048)->nullable();

            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);

            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image', 2048)->nullable();

            $table->string('twitter_card', 32)->default('summary_large_image');
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image', 2048)->nullable();

            $table->json('structured_data')->nullable();

            $table->timestamps();

            // One meta row per subject. The workspace is carried for scoping and
            // for the dashboard's per-workspace roll-up, not for uniqueness:
            // the subject id already implies its owner.
            $table->unique(['seoable_type', 'seoable_id']);
            $table->index(['company_id', 'seoable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
