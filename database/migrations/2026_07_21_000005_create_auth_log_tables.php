<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            // Retained even when no user matched, so credential-stuffing attempts
            // against non-existent accounts are still visible in the security log.
            $table->string('email')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 32)->nullable();
            $table->string('platform', 64)->nullable();
            $table->string('browser', 64)->nullable();
            $table->string('location')->nullable();

            $table->boolean('successful')->default(true);
            $table->string('failure_reason', 64)->nullable();
            $table->boolean('two_factor_used')->default(false);
            $table->string('session_id')->nullable()->index();

            $table->timestamp('logged_in_at');
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'logged_in_at']);
            $table->index(['successful', 'logged_in_at']);
        });

        Schema::create('security_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('event', 64)->index();
            $table->string('severity', 16)->default('info');
            $table->string('description');
            $table->json('context')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_logs');
        Schema::dropIfExists('login_histories');
    }
};
