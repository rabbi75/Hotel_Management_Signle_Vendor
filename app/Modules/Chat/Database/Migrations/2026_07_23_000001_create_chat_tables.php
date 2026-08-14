<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 16)->default('direct');
            $table->string('name')->nullable();
            $table->text('description')->nullable();

            // Canonical "smaller_id:larger_id" of the two participants of a
            // direct conversation. Deduplication has to be enforced by the
            // database, not by a find-or-create in PHP: two tabs opening the
            // same DM at once would otherwise both miss and both insert.
            $table->string('direct_key', 64)->nullable();

            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'direct_key']);
            $table->index(['company_id', 'last_message_at']);
        });

        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('muted_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'last_read_at']);
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('type', 16)->default('text');
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Cursor pagination walks the thread newest-first by primary key.
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index('message_id');
        });

        Schema::create('message_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 32);
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
