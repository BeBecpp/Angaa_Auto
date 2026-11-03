<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requests, scheduling, work orders, inspection, estimates, repair, QC and media.
     */
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('reference', 16)->unique();
            $table->string('guest_session_hash', 64)->index();
            $table->string('idempotency_key', 64);
            $table->string('request_type', 16);
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone_display', 32);
            $table->string('phone_normalized', 32)->index();
            $table->string('vehicle_text');
            $table->string('plate_display', 32)->nullable();
            $table->string('plate_normalized', 32)->nullable()->index();
            $table->unsignedSmallInteger('vehicle_year')->nullable();
            $table->text('symptoms');
            $table->date('preferred_local_date')->nullable();
            $table->string('preferred_window', 16)->nullable();
            $table->string('callout_address')->nullable();
            $table->decimal('callout_lat', 9, 6)->nullable();
            $table->decimal('callout_lng', 9, 6)->nullable();
            $table->timestamp('contact_consent_at');
            $table->string('contact_consent_version', 16);
            $table->boolean('reminder_preference')->default(false);
            $table->string('status', 16)->index();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('staff_notes')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('converted_appointment_id')->nullable()->index();
            $table->unsignedBigInteger('converted_work_order_id')->nullable()->index();
            $table->timestamp('converted_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->unique(['guest_session_hash', 'idempotency_key']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('service_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16)->default('workshop');
            $table->string('status', 16)->index();
            $table->timestamp('planned_start')->index();
            $table->timestamp('planned_end');
            $table->unsignedInteger('buffer_min')->default(0);
            $table->unsignedInteger('travel_min')->default(0);
            $table->foreignId('bay_id')->nullable()->constrained('shop_bays')->restrictOnDelete();
            $table->foreignId('mechanic_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('staff_notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('arrived_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('customer_change_request')->nullable();
            $table->timestamp('customer_change_requested_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('order_number', 24)->unique();
            $table->foreignId('service_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->index();
            $table->string('status_before_wait', 24)->nullable();
            $table->foreignId('assigned_mechanic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('bay_id')->nullable()->constrained('shop_bays')->nullOnDelete();
            $table->text('complaint');
            $table->unsignedInteger('intake_km')->nullable();
            $table->string('fuel_level', 16)->nullable();
            $table->text('belongings_note')->nullable();
            $table->text('visible_damage_note')->nullable();
            $table->json('customer_snapshot');
            $table->json('vehicle_snapshot');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamp('diagnosed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('promised_ready_at')->nullable();
            $table->unsignedBigInteger('current_approved_estimate_revision_id')->nullable();
            $table->foreignId('owner_override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('owner_override_at')->nullable();
            $table->string('owner_override_reason')->nullable();
            $table->string('unpaid_delivery_reason')->nullable();
            $table->text('customer_summary')->nullable();
            $table->text('declined_recommendations')->nullable();
            $table->string('warranty_terms')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['vehicle_id', 'received_at']);
        });

        Schema::create('order_number_sequences', function (Blueprint $table) {
            $table->string('key', 32)->primary();
            $table->unsignedInteger('next_value');
        });

        Schema::create('resource_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('resource_type', 16);
            $table->foreignId('bay_id')->nullable()->constrained('shop_bays')->restrictOnDelete();
            $table->foreignId('mechanic_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('active')->default(true);
            $table->timestamp('actual_occupied_from')->nullable();
            $table->timestamp('actual_occupied_to')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->timestamps();
            $table->index(['bay_id', 'active', 'starts_at', 'ends_at']);
            $table->index(['mechanic_user_id', 'active', 'starts_at', 'ends_at']);
        });

        Schema::create('work_order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['work_order_id', 'occurred_at']);
        });

        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->string('label');
            $table->string('condition', 16)->default('not_checked');
            $table->text('finding_internal')->nullable();
            $table->text('finding_customer')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('estimate_revisions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('state', 16)->index();
            $table->string('currency', 3)->default('MNT');
            $table->unsignedBigInteger('labor_total_mnt')->default(0);
            $table->unsignedBigInteger('parts_total_mnt')->default(0);
            $table->unsignedBigInteger('discount_total_mnt')->default(0);
            $table->unsignedBigInteger('tax_total_mnt')->default(0);
            $table->unsignedBigInteger('grand_total_mnt')->default(0);
            $table->text('diagnosis_summary')->nullable();
            $table->text('terms')->nullable();
            $table->text('exclusions')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('content_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['work_order_id', 'revision_number']);
        });

        Schema::create('estimate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_revision_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('description');
            $table->unsignedInteger('quantity_milli');
            $table->unsignedBigInteger('unit_price_mnt');
            $table->unsignedBigInteger('discount_mnt')->default(0);
            $table->unsignedBigInteger('tax_mnt')->default(0);
            $table->unsignedBigInteger('total_mnt');
            $table->string('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('estimate_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_revision_id')->unique()->constrained()->restrictOnDelete();
            $table->string('decision', 16);
            $table->string('channel', 16);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->unsignedBigInteger('amount_snapshot_mnt');
            $table->string('content_hash', 64);
            $table->text('staff_attestation_note')->nullable();
            $table->string('reason')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreign('current_approved_estimate_revision_id')->references('id')->on('estimate_revisions')->nullOnDelete();
        });

        Schema::create('repair_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('estimate_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->text('work_done')->nullable();
            $table->unsignedInteger('quantity_milli')->default(1000);
            $table->unsignedBigInteger('cost_mnt')->nullable();
            $table->boolean('is_part')->default(false);
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('qc_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->string('item');
            $table->string('result', 16);
            $table->string('note')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->timestamps();
        });

        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('upload_session_hash', 64)->nullable()->index();
            $table->string('upload_idempotency_key', 64)->nullable();
            $table->foreignId('service_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('inspection_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 32);
            $table->string('private_path');
            $table->string('preview_path')->nullable();
            $table->string('mime', 64);
            $table->unsignedInteger('byte_size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('category', 16)->index();
            $table->string('caption')->nullable();
            $table->boolean('customer_visible')->default(false);
            $table->string('processing_status', 16)->default('ready');
            $table->string('checksum', 64);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['current_approved_estimate_revision_id']);
        });

        foreach (['media_files', 'qc_checks', 'repair_items', 'estimate_decisions', 'estimate_lines', 'estimate_revisions', 'inspection_items', 'work_order_status_events', 'resource_reservations', 'order_number_sequences', 'work_orders', 'appointments', 'service_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
