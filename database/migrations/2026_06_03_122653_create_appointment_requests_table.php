<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('booking_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->date('preferred_date');
            $table->string('preferred_period');

            $table->unsignedInteger('total_duration_minutes');

            $table->string('patient_name');
            $table->string('patient_email')->nullable();
            $table->string('patient_phone')->nullable();
            $table->text('patient_note')->nullable();

            $table->string('status')->default('pending');

            $table->timestamps();

            $table->index(['branch_id', 'preferred_date', 'preferred_period', 'status']);
        });

        Schema::create('appointment_request_service', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appointment_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('service_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('duration_minutes_snapshot');
            $table->decimal('price_snapshot', 10, 2)->nullable();

            $table->timestamps();

            $table->unique(['appointment_request_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_request_service');
        Schema::dropIfExists('appointment_requests');
    }
};