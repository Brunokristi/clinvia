<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->after('service_id');
            $table->dateTime('ends_at')->nullable()->after('starts_at');

            $table->index(['branch_id', 'starts_at']);
            $table->index(['branch_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'starts_at']);
            $table->dropIndex(['branch_id', 'ends_at']);

            $table->dropColumn([
                'starts_at',
                'ends_at',
            ]);
        });
    }
};
