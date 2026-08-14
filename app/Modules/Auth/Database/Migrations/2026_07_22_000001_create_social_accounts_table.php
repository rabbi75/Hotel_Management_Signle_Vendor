<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('provider', 32);
            $table->string('provider_id');

            $table->string('email')->nullable();
            $table->string('nickname')->nullable();
            $table->string('avatar')->nullable();

            // Encrypted at rest by the model's casts.
            $table->text('token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            // One identity per provider, and one link per provider per account.
            $table->unique(['provider', 'provider_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
