<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->ulid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('website')->nullable();
            $table->string('tax_id', 64)->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 128)->nullable();
            $table->string('state', 128)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->char('country_code', 2)->nullable();

            $table->string('timezone', 64)->default('UTC');
            $table->char('currency', 3)->default('USD');
            $table->string('locale', 8)->default('en');

            $table->boolean('is_active')->default(true);
            $table->timestamp('trial_ends_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
