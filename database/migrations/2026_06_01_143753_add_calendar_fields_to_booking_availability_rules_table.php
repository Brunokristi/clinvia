<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_availability_rules', function (Blueprint $table) {
            $table->unsignedInteger('capacity')->default(1)->after('ends_at');

            $table->string('capacity_mode')->default('per_time')->after('capacity');
            // per_time = one booking per generated time
            // per_window = multiple bookings in the whole window

            $table->unsignedInteger('slot_step_minutes')->nullable()->after('capacity_mode');

            $table->boolean('repeats')->default(true)->after('slot_step_minutes');

            $table->string('repeat_frequency')->default('weekly')->after('repeats');
            // none, weekly

            $table->unsignedInteger('repeat_interval')->default(1)->after('repeat_frequency');

            $table->date('starts_on')->nullable()->after('repeat_interval');
            $table->date('ends_on')->nullable()->after('starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('booking_availability_rules', function (Blueprint $table) {
            $table->dropColumn([
                'capacity',
                'capacity_mode',
                'slot_step_minutes',
                'repeats',
                'repeat_frequency',
                'repeat_interval',
                'starts_on',
                'ends_on',
            ]);
        });
    }
};