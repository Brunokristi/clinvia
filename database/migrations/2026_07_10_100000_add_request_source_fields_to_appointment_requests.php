<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointment_requests', 'source_type')) {
                $table->string('source_type')->nullable()->after('group_event_id');
            }

            if (! Schema::hasColumn('appointment_requests', 'reservation_rule_id')) {
                $table->foreignId('reservation_rule_id')
                    ->nullable()
                    ->after('source_type')
                    ->constrained('events')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('appointment_requests', 'accepted_group_event_participation_id')) {
                $table->foreignId('accepted_group_event_participation_id')
                    ->nullable()
                    ->after('accepted_group_event_id')
                    ->constrained('group_event_participants')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('appointment_requests', 'accepted_group_event_participation_id')) {
                $table->dropConstrainedForeignId('accepted_group_event_participation_id');
            }

            if (Schema::hasColumn('appointment_requests', 'reservation_rule_id')) {
                $table->dropConstrainedForeignId('reservation_rule_id');
            }

            if (Schema::hasColumn('appointment_requests', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });
    }
};
