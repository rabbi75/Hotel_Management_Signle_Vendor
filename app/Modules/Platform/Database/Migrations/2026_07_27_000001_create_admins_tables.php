<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The operator console's own identity store.
 *
 * Deliberately a separate table and guard from `users`: a platform administrator
 * is not a tenant, holds no workspace membership, and must never be reachable
 * through the tenant login. Its own password-reset broker table keeps the two
 * reset flows from sharing tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->id();
            $table->ulid('uuid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('avatar_path')->nullable();
            $table->string('status', 16)->default('active');

            // Two-factor, powered by the same Fortify trait the User model uses.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('admin_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('admin_login_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('platform')->nullable();
            $table->string('browser')->nullable();
            $table->boolean('successful')->default(true);
            $table->timestamp('logged_in_at');

            $table->index(['admin_id', 'logged_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_login_histories');
        Schema::dropIfExists('admin_password_reset_tokens');
        Schema::dropIfExists('admins');
    }
};
