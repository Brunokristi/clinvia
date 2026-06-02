<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_bookable')->default(false)->after('description');
            $table->integer('capacity')->default(1)->after('is_bookable');
            $table->integer('buffer_before_minutes')->default(0)->after('capacity');
            $table->integer('buffer_after_minutes')->default(0)->after('buffer_before_minutes');
            $table->string('booking_type')->default('individual')->after('buffer_after_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'is_bookable',
                'capacity',
                'buffer_before_minutes',
                'buffer_after_minutes',
                'booking_type',
            ]);
        });
    }
};