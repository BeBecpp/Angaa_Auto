<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog, resources, customers and vehicles.
     */
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 96)->unique();
            $table->string('name_mn');
            $table->string('category', 64)->nullable()->index();
            $table->text('summary')->nullable();
            $table->text('description')->nullable();
            $table->text('inclusions')->nullable();
            $table->text('exclusions')->nullable();
            $table->string('price_type', 32);
            $table->unsignedBigInteger('price_mnt')->nullable();
            $table->unsignedInteger('estimated_duration_min')->nullable();
            $table->unsignedInteger('buffer_min')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('published')->default(false)->index();
            $table->boolean('is_demo')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shop_bays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->boolean('active')->default(true);
            $table->json('supported_categories')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('display_name', 96);
            $table->boolean('bookable')->default(false);
            $table->json('capabilities')->nullable();
            $table->timestamps();
        });

        Schema::create('opening_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16)->default('shop');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->unsignedTinyInteger('weekday');
            $table->string('local_start', 5);
            $table->string('local_end', 5);
            $table->date('active_from')->nullable();
            $table->date('active_until')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['scope', 'scope_id', 'weekday']);
        });

        Schema::create('closures', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16)->default('shop');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('reason');
            $table->timestamps();
            $table->index(['scope', 'scope_id', 'starts_at', 'ends_at']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('display_name');
            $table->string('contact_phone', 32)->nullable();
            $table->string('normalized_contact_phone', 32)->nullable()->index();
            $table->text('notes_internal')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('make', 64);
            $table->string('model', 64)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('vin', 32)->nullable()->index();
            $table->string('current_plate_display', 32)->nullable();
            $table->string('current_plate_normalized', 32)->nullable()->index();
            $table->boolean('is_demo')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_customer_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('relationship', 16)->default('owner');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('source', 32)->default('staff');
            $table->timestamps();
            $table->index(['customer_id', 'ends_at']);
            $table->index(['vehicle_id', 'ends_at']);
        });

        Schema::create('odometer_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('km');
            $table->timestamp('observed_at');
            $table->string('source', 16)->default('staff');
            $table->string('verification_state', 16)->default('verified');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('correction_of_id')->nullable()->constrained('odometer_readings')->nullOnDelete();
            $table->unsignedBigInteger('work_order_id')->nullable()->index();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        foreach (['odometer_readings', 'vehicle_customer_access', 'vehicles', 'customers', 'closures', 'opening_rules', 'staff_profiles', 'shop_bays', 'services', 'business_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
