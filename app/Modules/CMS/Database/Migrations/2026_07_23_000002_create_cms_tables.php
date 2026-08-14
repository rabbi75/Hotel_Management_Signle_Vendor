<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('status', 16)->default('draft');
            $table->string('layout', 64)->default('default');
            $table->json('seo')->nullable();
            $table->boolean('is_homepage')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Slugs are unique per workspace, not globally: two tenants may both
            // legitimately want /about.
            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status', 'published_at']);
        });

        Schema::create('page_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->unsignedInteger('order')->default(0);
            $table->json('data');
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['page_id', 'order']);
        });

        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location', 32)->default('header');
            $table->timestamps();

            $table->unique(['company_id', 'location']);
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('label');
            $table->string('url')->nullable();
            $table->string('target', 16)->default('_self');
            $table->string('icon', 64)->nullable();

            // Optional permission gate: the item only renders for a visitor who
            // holds it. Null means "everyone", including guests.
            $table->string('permission')->nullable();

            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('page_blocks');
        Schema::dropIfExists('pages');
    }
};
