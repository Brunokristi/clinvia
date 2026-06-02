<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_availability_rules', function (Blueprint $table) {
            $table->date('repeat_ends_on')->nullable()->after('repeat_unit');
            $table->json('excluded_dates')->nullable()->after('repeat_ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('booking_availability_rules', function (Blueprint $table) {
            $table->dropColumn([
                'repeat_ends_on',
                'excluded_dates',
            ]);
        });
    }
};
