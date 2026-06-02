<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_availability_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('booking_availability_rules', 'date')) {
                $table->date('date')->nullable()->after('day_of_week');
            }

            if (! Schema::hasColumn('booking_availability_rules', 'slot_mode')) {
                $table->string('slot_mode')->default('free_bookable_time')->after('ends_at');
            }

            if (! Schema::hasColumn('booking_availability_rules', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('slot_mode')->constrained('services')->nullOnDelete();
            }

            if (! Schema::hasColumn('booking_availability_rules', 'service_ids')) {
                $table->json('service_ids')->nullable()->after('service_id');
            }

            if (! Schema::hasColumn('booking_availability_rules', 'bookable_places')) {
                $table->integer('bookable_places')->default(1)->after('service_ids');
            }

            if (! Schema::hasColumn('booking_availability_rules', 'repeats')) {
                $table->boolean('repeats')->default(false)->after('bookable_places');
            }

            if (! Schema::hasColumn('booking_availability_rules', 'repeat_every')) {
                $table->integer('repeat_every')->default(1)->after('repeats');
            }

            if (! Schema::hasColumn('booking_availability_rules', 'repeat_unit')) {
                $table->string('repeat_unit')->default('weeks')->after('repeat_every');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_availability_rules', function (Blueprint $table) {
            if (Schema::hasColumn('booking_availability_rules', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }

            $columns = array_filter([
                Schema::hasColumn('booking_availability_rules', 'date') ? 'date' : null,
                Schema::hasColumn('booking_availability_rules', 'slot_mode') ? 'slot_mode' : null,
                Schema::hasColumn('booking_availability_rules', 'service_ids') ? 'service_ids' : null,
                Schema::hasColumn('booking_availability_rules', 'bookable_places') ? 'bookable_places' : null,
                Schema::hasColumn('booking_availability_rules', 'repeats') ? 'repeats' : null,
                Schema::hasColumn('booking_availability_rules', 'repeat_every') ? 'repeat_every' : null,
                Schema::hasColumn('booking_availability_rules', 'repeat_unit') ? 'repeat_unit' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};