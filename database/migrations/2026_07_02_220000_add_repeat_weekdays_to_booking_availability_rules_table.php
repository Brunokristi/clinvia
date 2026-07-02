<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_availability_rules', function (Blueprint $table): void {
            if (!Schema::hasColumn('booking_availability_rules', 'repeat_weekdays')) {
                $table->json('repeat_weekdays')->nullable()->after('repeat_unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_availability_rules', function (Blueprint $table): void {
            if (Schema::hasColumn('booking_availability_rules', 'repeat_weekdays')) {
                $table->dropColumn('repeat_weekdays');
            }
        });
    }
};
