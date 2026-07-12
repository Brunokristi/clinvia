<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            if (! Schema::hasColumn('patients', 'birth_number_encrypted')) {
                $table->text('birth_number_encrypted')->nullable()->after('patient_birth_number');
            }

            if (! Schema::hasColumn('patients', 'birth_number_hash')) {
                $table->string('birth_number_hash', 64)->nullable()->after('birth_number_encrypted');
                $table->index('birth_number_hash', 'patients_birth_number_hash_idx');
            }
        });

        Schema::table('appointment_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointment_requests', 'patient_match_status')) {
                $table->string('patient_match_status')->default('pending')->after('patient_id');
            }

            if (! Schema::hasColumn('appointment_requests', 'possible_patient_id')) {
                $table->foreignId('possible_patient_id')
                    ->nullable()
                    ->after('patient_match_status')
                    ->constrained('patients')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('appointment_requests', 'contact_change_status')) {
                $table->string('contact_change_status')->default('none')->after('possible_patient_id');
            }

            if (! Schema::hasColumn('appointment_requests', 'submitted_birth_number_encrypted')) {
                $table->text('submitted_birth_number_encrypted')->nullable()->after('patient_birth_number');
            }

            if (! Schema::hasColumn('appointment_requests', 'submitted_birth_number_hash')) {
                $table->string('submitted_birth_number_hash', 64)->nullable()->after('submitted_birth_number_encrypted');
                $table->index('submitted_birth_number_hash', 'appointment_requests_submitted_birth_hash_idx');
            }

            if (! Schema::hasColumn('appointment_requests', 'patient_data_differences')) {
                $table->json('patient_data_differences')->nullable()->after('contact_change_status');
            }

            if (! Schema::hasColumn('appointment_requests', 'patient_match_reviewed_at')) {
                $table->dateTime('patient_match_reviewed_at')->nullable()->after('patient_data_differences');
            }

            if (! Schema::hasColumn('appointment_requests', 'patient_match_reviewed_by')) {
                $table->foreignId('patient_match_reviewed_by')
                    ->nullable()
                    ->after('patient_match_reviewed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('appointment_requests', 'patient_match_note')) {
                $table->text('patient_match_note')->nullable()->after('patient_match_reviewed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('appointment_requests', 'patient_match_reviewed_by')) {
                $table->dropConstrainedForeignId('patient_match_reviewed_by');
            }

            if (Schema::hasColumn('appointment_requests', 'possible_patient_id')) {
                $table->dropConstrainedForeignId('possible_patient_id');
            }

            if (Schema::hasColumn('appointment_requests', 'patient_match_note')) {
                $table->dropColumn('patient_match_note');
            }

            if (Schema::hasColumn('appointment_requests', 'patient_match_reviewed_at')) {
                $table->dropColumn('patient_match_reviewed_at');
            }

            if (Schema::hasColumn('appointment_requests', 'patient_data_differences')) {
                $table->dropColumn('patient_data_differences');
            }

            if (Schema::hasColumn('appointment_requests', 'contact_change_status')) {
                $table->dropColumn('contact_change_status');
            }

            if (Schema::hasColumn('appointment_requests', 'patient_match_status')) {
                $table->dropColumn('patient_match_status');
            }

            if (Schema::hasColumn('appointment_requests', 'submitted_birth_number_hash')) {
                $table->dropIndex('appointment_requests_submitted_birth_hash_idx');
                $table->dropColumn('submitted_birth_number_hash');
            }

            if (Schema::hasColumn('appointment_requests', 'submitted_birth_number_encrypted')) {
                $table->dropColumn('submitted_birth_number_encrypted');
            }
        });

        Schema::table('patients', function (Blueprint $table): void {
            if (Schema::hasColumn('patients', 'birth_number_hash')) {
                $table->dropIndex('patients_birth_number_hash_idx');
                $table->dropColumn('birth_number_hash');
            }

            if (Schema::hasColumn('patients', 'birth_number_encrypted')) {
                $table->dropColumn('birth_number_encrypted');
            }
        });
    }
};
