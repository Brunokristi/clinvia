<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capacity_windows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('booking_availability_rule_id')
                ->nullable()
                ->constrained('booking_availability_rules')
                ->nullOnDelete();

            $table->foreignId('service_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->unsignedInteger('capacity')->default(1);
            $table->string('status')->default('active');

            $table->timestamps();

            $table->index(['branch_id', 'starts_at']);
            $table->index(['booking_availability_rule_id', 'starts_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('capacity_window_id')
                ->nullable()
                ->after('service_id')
                ->constrained('capacity_windows')
                ->nullOnDelete();

            $table->index(['capacity_window_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('capacity_window_id');
        });

        Schema::dropIfExists('capacity_windows');
    }
};