<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sanctum owns this table; the public API adds the workspace a token is
        // bound to so a leaked token can never widen its own tenant scope.
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('tokenable_type')->constrained()->cascadeOnDelete();
        });

        Schema::create('api_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('api_token_id')->nullable();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->string('route_name')->nullable();
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['api_token_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('url', 2048);
            $table->string('description')->nullable();
            $table->json('events');

            // Encrypted at rest: possession of the secret is enough to forge a
            // delivery that the receiver will accept as genuine.
            $table->text('secret');

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event', 120);
            $table->json('payload');
            $table->unsignedInteger('attempt')->default(0);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'created_at']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('api_request_logs');

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
