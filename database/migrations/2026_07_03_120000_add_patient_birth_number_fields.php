<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->string('patient_birth_number')->nullable()->after('patient_phone');
            $table->index(['branch_id', 'patient_birth_number'], 'patients_branch_birth_number_idx');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('patient_birth_number')->nullable()->after('patient_phone');
        });

        Schema::table('appointment_requests', function (Blueprint $table): void {
            $table->string('patient_birth_number')->nullable()->after('patient_phone');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table): void {
            $table->dropColumn('patient_birth_number');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('patient_birth_number');
        });

        Schema::table('patients', function (Blueprint $table): void {
            $table->dropIndex('patients_branch_birth_number_idx');
            $table->dropColumn('patient_birth_number');
        });
    }
};
