<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'split_from_event_id')) {
                $table->foreignId('split_from_event_id')
                    ->nullable()
                    ->after('recurrence_original_ends_at')
                    ->constrained('events')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('events', 'recurrence_sequence')) {
                $table->unsignedInteger('recurrence_sequence')
                    ->nullable()
                    ->after('split_from_event_id');
            }
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->index(['split_from_event_id'], 'events_split_from_event_id_index');
            $table->unique(['recurrence_parent_id', 'recurrence_original_starts_at'], 'events_recurrence_override_unique');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropUnique('events_recurrence_override_unique');
            $table->dropIndex('events_split_from_event_id_index');

            if (Schema::hasColumn('events', 'split_from_event_id')) {
                $table->dropConstrainedForeignId('split_from_event_id');
            }

            if (Schema::hasColumn('events', 'recurrence_sequence')) {
                $table->dropColumn('recurrence_sequence');
            }
        });
    }
};