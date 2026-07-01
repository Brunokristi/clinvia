<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'series_uuid')) {
                $table->uuid('series_uuid')->nullable()->after('capacity_window_id');
            }

            if (! Schema::hasColumn('bookings', 'recurrence')) {
                $table->json('recurrence')->nullable()->after('series_uuid');
            }

            if (! Schema::hasColumn('bookings', 'recurrence_excluded_dates')) {
                $table->json('recurrence_excluded_dates')->nullable()->after('recurrence');
            }

            $table->index(['branch_id', 'series_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'series_uuid']);

            if (Schema::hasColumn('bookings', 'recurrence_excluded_dates')) {
                $table->dropColumn('recurrence_excluded_dates');
            }

            if (Schema::hasColumn('bookings', 'recurrence')) {
                $table->dropColumn('recurrence');
            }

            if (Schema::hasColumn('bookings', 'series_uuid')) {
                $table->dropColumn('series_uuid');
            }
        });
    }
};