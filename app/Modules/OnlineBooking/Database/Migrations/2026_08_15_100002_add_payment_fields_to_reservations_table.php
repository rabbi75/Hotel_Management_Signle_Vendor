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
            $table->foreignId('customer_id')->nullable()->after('guest_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->after('booking_source')->constrained('booking_payment_methods')->nullOnDelete();
            $table->string('payment_status')->default('unpaid')->after('paid_amount');
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn(['payment_status', 'payment_reference', 'paid_at']);
        });
    }
};
