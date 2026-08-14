<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->ulid('uuid')->unique();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('check_in_time', 8)->default('14:00');
            $table->string('check_out_time', 8)->default('11:00');
            $table->char('currency', 3)->default('USD');
            $table->string('timezone')->default('UTC');
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->string('tax_name')->nullable();
            $table->text('policies')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('status', 32)->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('hotel_buildings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'hotel_id']);
            $table->unique(['hotel_id', 'name']);
        });

        Schema::create('hotel_floors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained('hotel_buildings')->nullOnDelete();
            $table->string('name');
            $table->integer('floor_number')->default(0);
            $table->string('code', 64)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'hotel_id']);
            $table->index(['hotel_id', 'building_id']);
        });

        Schema::create('room_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('base_price')->default(0);
            $table->unsignedSmallInteger('max_adults')->default(2);
            $table->unsignedSmallInteger('max_children')->default(0);
            $table->unsignedSmallInteger('max_occupancy')->default(2);
            $table->string('bed_configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'hotel_id']);
            $table->unique(['hotel_id', 'name']);
        });

        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained('hotel_buildings')->nullOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained('hotel_floors')->nullOnDelete();
            $table->foreignId('room_type_id')->nullable()->constrained('room_types')->nullOnDelete();
            $table->string('number');
            $table->string('code', 64)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('base_price')->nullable();
            $table->unsignedSmallInteger('max_occupancy')->nullable();
            $table->string('status', 32)->default('available');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['hotel_id', 'number']);
            $table->index(['company_id', 'hotel_id']);
            $table->index(['hotel_id', 'status']);
            $table->index(['floor_id', 'room_type_id']);
        });

        Schema::create('beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained('hotel_floors')->nullOnDelete();
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->string('bed_type')->nullable();
            $table->unsignedBigInteger('price')->default(0);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('available');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['room_id', 'name']);
            $table->index(['company_id', 'hotel_id']);
            $table->index(['hotel_id', 'status']);
        });

        Schema::create('facilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained('hotels')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'hotel_id']);
        });

        Schema::create('facilityables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->morphs('facilityable');
            $table->timestamps();

            $table->unique(['facility_id', 'facilityable_type', 'facilityable_id'], 'facilityables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilityables');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('hotel_floors');
        Schema::dropIfExists('hotel_buildings');
        Schema::dropIfExists('hotels');
    }
};
