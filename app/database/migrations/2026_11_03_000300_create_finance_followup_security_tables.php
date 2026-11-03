<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invoices, payments, maintenance plans, consent, outbox, OTP and audit.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('invoice_number', 24)->nullable()->unique();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('estimate_revision_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 16)->index();
            $table->unsignedBigInteger('labor_total_mnt')->default(0);
            $table->unsignedBigInteger('parts_total_mnt')->default(0);
            $table->unsignedBigInteger('discount_total_mnt')->default(0);
            $table->unsignedBigInteger('tax_total_mnt')->default(0);
            $table->unsignedBigInteger('grand_total_mnt')->default(0);
            $table->json('issue_snapshot')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->string('note')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('description');
            $table->unsignedInteger('quantity_milli');
            $table->unsignedBigInteger('unit_price_mnt');
            $table->unsignedBigInteger('discount_mnt')->default(0);
            $table->unsignedBigInteger('tax_mnt')->default(0);
            $table->unsignedBigInteger('total_mnt');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_mnt');
            $table->string('method', 24);
            $table->string('status', 16)->default('settled');
            $table->string('reference')->nullable();
            $table->timestamp('received_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_mnt');
            $table->string('reason');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->date('due_date')->nullable();
            $table->unsignedInteger('due_km')->nullable();
            $table->foreignId('based_on_reading_id')->nullable()->constrained('odometer_readings')->nullOnDelete();
            $table->text('recommendation_source_note')->nullable();
            $table->string('status', 16)->index();
            $table->foreignId('supersedes_id')->nullable()->constrained('maintenance_plans')->nullOnDelete();
            $table->foreignId('satisfied_by_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('communication_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('phone_normalized', 32);
            $table->string('purpose', 24);
            $table->string('channel', 16)->default('sms');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('source', 32);
            $table->string('policy_version', 16);
            $table->timestamps();
            $table->index(['customer_id', 'purpose', 'channel']);
        });

        Schema::create('notification_outbox', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('aggregate_type', 32);
            $table->unsignedBigInteger('aggregate_id');
            $table->unsignedInteger('aggregate_version');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('recipient_phone', 32);
            $table->string('channel', 16)->default('sms');
            $table->string('template_key', 48);
            $table->string('template_version', 8)->default('1');
            $table->string('lifecycle_stage', 32);
            $table->timestamp('scheduled_for');
            $table->string('idempotency_key', 128)->unique();
            $table->json('payload_minimal')->nullable();
            $table->string('status', 24)->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('claim_token', 36)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->string('skip_reason', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('manual_call_completed_at')->nullable();
            $table->foreignId('manual_call_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index(['status', 'scheduled_for']);
        });

        Schema::create('message_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_outbox_id')->constrained('notification_outbox')->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('adapter', 32);
            $table->string('provider_reference')->nullable();
            $table->string('status', 24);
            $table->string('normalized_error', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('phone_normalized', 32)->index();
            $table->string('hashed_code');
            $table->string('session_binding', 64)->index();
            $table->timestamp('expires_at');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('resend_available_at');
            $table->string('local_preview_code', 8)->nullable();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64)->index();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('context')->nullable();
            $table->string('reason')->nullable();
            $table->string('correlation_id', 36)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('system_heartbeats', function (Blueprint $table) {
            $table->string('name', 32)->primary();
            $table->timestamp('beat_at');
        });
    }

    public function down(): void
    {
        foreach (['system_heartbeats', 'audit_events', 'otp_challenges', 'message_attempts', 'notification_outbox', 'communication_consents', 'maintenance_plans', 'payment_reversals', 'payments', 'invoice_lines', 'invoices'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
