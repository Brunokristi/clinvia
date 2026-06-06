<?php

use App\Models\AppointmentRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_inbox_messages', function (Blueprint $table) {
            $table
                ->foreignIdFor(AppointmentRequest::class)
                ->nullable()
                ->after('booking_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('branch_inbox_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(AppointmentRequest::class);
        });
    }
};