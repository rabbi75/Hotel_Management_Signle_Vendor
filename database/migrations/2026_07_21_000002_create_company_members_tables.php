<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable();
            $table->string('role', 32)->default('member');
            $table->string('job_title')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 16)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('team_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });

        // Deferred until `departments` exists, since membership references it.
        Schema::table('company_user', function (Blueprint $table): void {
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::create('company_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 32)->default('member');
            $table->string('status', 16)->default('pending');
            $table->string('token', 64)->unique();
            $table->json('permission_roles')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'email']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_invitations');
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');

        Schema::table('company_user', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
        });

        Schema::dropIfExists('departments');
        Schema::dropIfExists('company_user');
    }
};
