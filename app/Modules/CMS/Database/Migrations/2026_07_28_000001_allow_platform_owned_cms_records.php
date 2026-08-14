<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets CMS records exist above tenancy.
 *
 * A page with a null `company_id` belongs to the platform itself rather than to
 * any workspace: it is the public marketing site, authored from the operator
 * console. CompanyScope already ignores rows it cannot match once no workspace
 * is active, so nothing else needs to know.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->foreignId('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();

            // `created_by` points at `users`, and an operator is not one. The
            // console stamps this instead so authorship stays honest.
            $table->foreignId('created_by_admin_id')->nullable()->after('created_by')
                ->constrained('admins')->nullOnDelete();
        });

        Schema::table('page_blocks', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->foreignId('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::table('menus', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->foreignId('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->foreignId('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Platform-owned rows have no workspace to fall back to, so they are
        // removed rather than reassigned to an arbitrary tenant.
        foreach (['menu_items', 'menus', 'page_blocks', 'pages'] as $table) {
            DB::table($table)->whereNull('company_id')->delete();
        }

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_admin_id');
        });

        foreach (['pages', 'page_blocks', 'menus', 'menu_items'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
                $table->foreignId('company_id')->nullable(false)->change();
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            });
        }
    }
};
