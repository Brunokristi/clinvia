<?php

use App\Models\Branch;
use App\Models\Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Branch::class)->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_availability_rule_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_availability_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Service::class)->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['booking_availability_rule_id', 'service_id'], 'booking_rule_service_unique');
        });

        Schema::create('booking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Branch::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Service::class)->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->integer('capacity')->default(1);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'service_id', 'starts_at', 'ends_at'], 'booking_slot_unique');
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_slot_id')->constrained('booking_slots')->cascadeOnDelete();
            $table->foreignIdFor(Branch::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Service::class)->constrained()->cascadeOnDelete();
            $table->string('patient_name');
            $table->string('patient_email')->nullable();
            $table->string('patient_phone')->nullable();
            $table->string('status')->default('confirmed');
            $table->text('patient_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Branch::class)->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('sender_phone')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'type']);
            $table->index(['branch_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_inbox_messages');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_slots');
        Schema::dropIfExists('booking_availability_rule_service');
        Schema::dropIfExists('booking_availability_rules');
    }
};