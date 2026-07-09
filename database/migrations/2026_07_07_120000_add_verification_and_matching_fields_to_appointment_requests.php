<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table): void {
            $table->string('first_name')->nullable()->after('booking_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('normalized_email')->nullable()->after('patient_email');
            $table->string('normalized_phone')->nullable()->after('patient_phone');
            $table->date('date_of_birth')->nullable()->after('patient_birth_number');
            $table->dateTime('preferred_starts_at')->nullable()->after('preferred_period');
            $table->string('preferred_time_note')->nullable()->after('preferred_starts_at');
            $table->dateTime('privacy_consent_accepted_at')->nullable()->after('patient_note');
            $table->dateTime('email_verified_at')->nullable()->after('status');
            $table->string('verification_token_hash')->nullable()->after('email_verified_at');
            $table->dateTime('verification_expires_at')->nullable()->after('verification_token_hash');
            $table->foreignId('patient_id')->nullable()->after('verification_expires_at')->constrained('patients')->nullOnDelete();
            $table->foreignId('accepted_booking_id')->nullable()->after('patient_id')->constrained('events')->nullOnDelete();
            $table->text('rejected_reason')->nullable()->after('accepted_booking_id');
            $table->dateTime('manually_verified_at')->nullable()->after('rejected_reason');
            $table->foreignId('manually_verified_by')->nullable()->after('manually_verified_at')->constrained('users')->nullOnDelete();
            $table->text('manual_verification_reason')->nullable()->after('manually_verified_by');

            $table->index(['branch_id', 'status']);
            $table->index(['normalized_email']);
            $table->index(['normalized_phone']);
            $table->index(['verification_expires_at']);
            $table->index(['patient_id']);
        });

        Schema::table('booking_event_details', function (Blueprint $table): void {
            $table->foreignId('source_request_id')->nullable()->after('patient_id')->constrained('appointment_requests')->nullOnDelete();
            $table->index(['source_request_id']);
        });

        Schema::create('appointment_request_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['appointment_request_id', 'action']);
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_request_audit_logs');

        Schema::table('booking_event_details', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_request_id');
        });

        Schema::table('appointment_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manually_verified_by');
            $table->dropConstrainedForeignId('accepted_booking_id');
            $table->dropConstrainedForeignId('patient_id');
            $table->dropColumn([
                'first_name',
                'last_name',
                'normalized_email',
                'normalized_phone',
                'date_of_birth',
                'preferred_starts_at',
                'preferred_time_note',
                'privacy_consent_accepted_at',
                'email_verified_at',
                'verification_token_hash',
                'verification_expires_at',
                'rejected_reason',
                'manually_verified_at',
                'manual_verification_reason',
            ]);
        });
    }
};
