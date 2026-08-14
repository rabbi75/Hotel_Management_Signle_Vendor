<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->string('external_reference')->nullable()->after('booking_source');
            $table->json('channel_metadata')->nullable()->after('external_reference');

            $table->index(['company_id', 'external_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'external_reference']);
            $table->dropColumn(['external_reference', 'channel_metadata']);
        });
    }
};
