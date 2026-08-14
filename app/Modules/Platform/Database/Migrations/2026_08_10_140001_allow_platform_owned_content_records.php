<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets blog and media records belong to the platform (null company_id).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    protected array $tables = [
        'blog_posts',
        'blog_categories',
        'blog_tags',
        'blog_comments',
        'media_folders',
        'media_assets',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasTable($name) || ! Schema::hasColumn($name, 'company_id')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
            });

            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->change();
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }

            DB::table($name)->whereNull('company_id')->delete();

            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
            });

            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable(false)->change();
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            });
        }
    }
};
