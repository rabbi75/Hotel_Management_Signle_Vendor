<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attribute security-log entries to a platform admin.
 *
 * A console action (impersonation, a plan change, a suspension) is taken by an
 * Admin, not a tenant User, so `user_id` alone cannot record who did it. This
 * nullable column carries the admin's identity alongside the existing tenant
 * fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_logs', function (Blueprint $table): void {
            $table->foreignId('admin_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('security_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('admin_id');
        });
    }
};
