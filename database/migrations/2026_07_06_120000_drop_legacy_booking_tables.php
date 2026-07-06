<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('appointment_requests') && Schema::hasColumn('appointment_requests', 'booking_id')) {
            Schema::table('appointment_requests', function (Blueprint $table): void {
                $table->dropForeign(['booking_id']);
            });
        }

        if (Schema::hasTable('branch_inbox_messages') && Schema::hasColumn('branch_inbox_messages', 'booking_id')) {
            Schema::table('branch_inbox_messages', function (Blueprint $table): void {
                $table->dropForeign(['booking_id']);
            });
        }

        Schema::dropIfExists('booking_service');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_slots');
        Schema::dropIfExists('booking_availability_rule_service');
        Schema::dropIfExists('booking_availability_rules');
        Schema::dropIfExists('capacity_windows');
    }

    public function down(): void
    {
        // Legacy booking tables were intentionally removed in development hard cutover.
    }
};
