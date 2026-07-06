<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->default('confirmed');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('timezone')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->json('recurrence_rule')->nullable();
            $table->foreignId('recurrence_parent_id')->nullable()->constrained('events')->nullOnDelete();
            $table->date('recurrence_exception_date')->nullable();
            $table->dateTime('recurrence_original_starts_at')->nullable();
            $table->dateTime('recurrence_original_ends_at')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'starts_at']);
            $table->index(['branch_id', 'ends_at']);
            $table->index(['branch_id', 'type']);
            $table->index(['branch_id', 'status']);
            $table->index(['recurrence_parent_id']);
            $table->index(['branch_id', 'recurrence_exception_date']);
        });

        Schema::create('booking_event_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('booking_source')->nullable();
            $table->string('booking_status')->default('confirmed');
            $table->text('internal_notes')->nullable();
            $table->text('public_notes')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('patient_email')->nullable();
            $table->string('patient_phone')->nullable();
            $table->string('patient_birth_number')->nullable();
            $table->json('contact_snapshot')->nullable();
            $table->timestamps();

            $table->index(['patient_id']);
            $table->index(['booking_status']);
        });

        Schema::create('availability_rule_event_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->json('capacity_rules')->nullable();
            $table->json('visibility_rules')->nullable();
            $table->unsignedInteger('min_booking_notice_minutes')->nullable();
            $table->unsignedInteger('max_booking_notice_minutes')->nullable();
            $table->unsignedInteger('slot_interval_minutes')->nullable();
            $table->unsignedInteger('buffer_before_minutes')->nullable();
            $table->unsignedInteger('buffer_after_minutes')->nullable();
            $table->json('online_booking_rules')->nullable();
            $table->timestamps();
        });

        Schema::create('group_event_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('service_name')->nullable();
            $table->unsignedInteger('capacity')->default(1);
            $table->unsignedInteger('reserved_places')->default(0);
            $table->string('group_status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['service_id']);
            $table->index(['group_status']);
        });

        Schema::create('event_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->unsignedInteger('duration_minutes_snapshot')->nullable();
            $table->decimal('price_snapshot', 12, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['event_id', 'service_id']);
        });

        Schema::create('group_event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('status')->default('confirmed');
            $table->dateTime('booked_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('participant_name')->nullable();
            $table->string('participant_email')->nullable();
            $table->string('participant_phone')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['patient_id']);
        });

        Schema::create('calendar_legacy_event_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_type');
            $table->unsignedBigInteger('legacy_id');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['legacy_type', 'legacy_id']);
            $table->index(['branch_id', 'legacy_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_legacy_event_maps');
        Schema::dropIfExists('group_event_participants');
        Schema::dropIfExists('event_service');
        Schema::dropIfExists('group_event_details');
        Schema::dropIfExists('availability_rule_event_details');
        Schema::dropIfExists('booking_event_details');
        Schema::dropIfExists('events');
    }
};
