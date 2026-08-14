<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();

            // scope = system | company | user. scope_id is null for `system`,
            // so no foreign key can be declared here; integrity is enforced by
            // the repository, which is the only writer.
            $table->string('scope', 16)->default('system');
            $table->unsignedBigInteger('scope_id')->nullable();

            $table->string('group', 64)->index();
            $table->string('key');
            $table->json('value')->nullable();
            $table->boolean('is_encrypted')->default(false);

            $table->timestamps();

            $table->unique(['scope', 'scope_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
