<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();

            // Slugs are unique per workspace, not globally: two customers must
            // both be able to own /blog/category/news.
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('blog_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();

            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();

            // The sanitised render of `body`. Cached because converting markdown
            // on every public request is pure waste; rebuilt on every save.
            $table->longText('body_html')->nullable();

            $table->string('body_format', 16)->default('markdown');
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->string('featured_image', 2048)->nullable();
            $table->unsignedInteger('reading_time')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->json('seo')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status', 'published_at']);
            $table->index(['category_id', 'status']);
        });

        Schema::create('blog_post_tag', function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('blog_tags')->cascadeOnDelete();

            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('blog_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('blog_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Present only for comments left by someone without an account.
            $table->string('guest_name', 120)->nullable();
            $table->string('guest_email')->nullable();

            $table->text('body');
            $table->string('status', 16)->default('pending');
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['post_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_categories');
    }
};
