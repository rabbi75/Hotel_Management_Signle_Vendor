<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');

            // Materialised path of slugs ("marketing/2026/q1"). Denormalised so
            // a breadcrumb or a subtree query is one indexed read rather than a
            // walk up the tree.
            $table->string('path', 1024);

            $table->timestamps();

            $table->index(['company_id', 'parent_id']);
            $table->unique(['company_id', 'parent_id', 'slug']);
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            // Set when this asset is an edited version of another; the original
            // is never destroyed by the editor.
            $table->foreignId('original_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();

            $table->string('name');
            $table->string('title')->nullable();
            $table->string('alt')->nullable();
            $table->text('caption')->nullable();
            $table->json('tags')->nullable();

            // SHA-256 of the file contents, for duplicate detection.
            $table->char('content_hash', 64);

            $table->string('mime_type', 191);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('disk', 64);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'folder_id']);
            $table->index(['company_id', 'content_hash']);
            $table->index(['company_id', 'mime_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('media_folders');
    }
};
