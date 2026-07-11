<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointment_requests', 'service_id')) {
                $table->foreignId('service_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('services')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('appointment_requests', 'group_event_id')) {
                $table->foreignId('group_event_id')
                    ->nullable()
                    ->after('booking_id')
                    ->constrained('events')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('appointment_requests', 'group_event_occurrence_original_start_at')) {
                $table->dateTime('group_event_occurrence_original_start_at')->nullable()->after('group_event_id');
            }

            if (! Schema::hasColumn('appointment_requests', 'requested_starts_at')) {
                $table->dateTime('requested_starts_at')->nullable()->after('group_event_occurrence_original_start_at');
            }

            if (! Schema::hasColumn('appointment_requests', 'requested_ends_at')) {
                $table->dateTime('requested_ends_at')->nullable()->after('requested_starts_at');
            }

            if (! Schema::hasColumn('appointment_requests', 'requested_group_event_starts_at')) {
                $table->dateTime('requested_group_event_starts_at')->nullable()->after('requested_ends_at');
            }

            if (! Schema::hasColumn('appointment_requests', 'requested_group_event_ends_at')) {
                $table->dateTime('requested_group_event_ends_at')->nullable()->after('requested_group_event_starts_at');
            }

            if (! Schema::hasColumn('appointment_requests', 'accepted_group_event_id')) {
                $table->foreignId('accepted_group_event_id')
                    ->nullable()
                    ->after('accepted_booking_id')
                    ->constrained('events')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('appointment_requests', 'accepted_group_event_occurrence_original_start_at')) {
                $table->dateTime('accepted_group_event_occurrence_original_start_at')->nullable()->after('accepted_group_event_id');
            }
        });

        Schema::table('group_event_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('group_event_participants', 'source_request_id')) {
                $table->foreignId('source_request_id')
                    ->nullable()
                    ->after('event_id')
                    ->constrained('appointment_requests')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_event_participants', function (Blueprint $table): void {
            if (Schema::hasColumn('group_event_participants', 'source_request_id')) {
                $table->dropConstrainedForeignId('source_request_id');
            }
        });

        Schema::table('appointment_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('appointment_requests', 'accepted_group_event_occurrence_original_start_at')) {
                $table->dropColumn('accepted_group_event_occurrence_original_start_at');
            }

            if (Schema::hasColumn('appointment_requests', 'accepted_group_event_id')) {
                $table->dropConstrainedForeignId('accepted_group_event_id');
            }

            if (Schema::hasColumn('appointment_requests', 'requested_group_event_ends_at')) {
                $table->dropColumn('requested_group_event_ends_at');
            }

            if (Schema::hasColumn('appointment_requests', 'requested_group_event_starts_at')) {
                $table->dropColumn('requested_group_event_starts_at');
            }

            if (Schema::hasColumn('appointment_requests', 'requested_ends_at')) {
                $table->dropColumn('requested_ends_at');
            }

            if (Schema::hasColumn('appointment_requests', 'requested_starts_at')) {
                $table->dropColumn('requested_starts_at');
            }

            if (Schema::hasColumn('appointment_requests', 'group_event_occurrence_original_start_at')) {
                $table->dropColumn('group_event_occurrence_original_start_at');
            }

            if (Schema::hasColumn('appointment_requests', 'group_event_id')) {
                $table->dropConstrainedForeignId('group_event_id');
            }

            if (Schema::hasColumn('appointment_requests', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
        });
    }
};
