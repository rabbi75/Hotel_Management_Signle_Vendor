<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->ulid('uuid')->unique()->after('id');

            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('job_title')->nullable()->after('phone');
            $table->text('bio')->nullable()->after('job_title');

            $table->string('status', 16)->default('active')->after('bio');
            $table->foreignId('current_company_id')->nullable()->after('status')
                ->constrained('companies')->nullOnDelete();

            $table->string('timezone', 64)->default('UTC')->after('current_company_id');
            $table->string('locale', 8)->default('en')->after('timezone');
            $table->string('theme', 16)->default('system')->after('locale');
            $table->json('preferences')->nullable()->after('theme');

            $table->timestamp('last_login_at')->nullable()->after('preferences');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_ip');
            $table->timestamp('suspended_at')->nullable()->after('password_changed_at');
            $table->string('suspended_reason')->nullable()->after('suspended_at');

            $table->softDeletes();

            $table->index('status');
            $table->index('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['current_company_id']);
            $table->dropColumn([
                'uuid', 'first_name', 'last_name', 'phone', 'job_title', 'bio',
                'status', 'current_company_id', 'timezone', 'locale', 'theme',
                'preferences', 'last_login_at', 'last_login_ip',
                'password_changed_at', 'suspended_at', 'suspended_reason', 'deleted_at',
            ]);
        });
    }
};
