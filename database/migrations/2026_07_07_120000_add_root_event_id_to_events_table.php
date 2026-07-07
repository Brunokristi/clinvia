<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'root_event_id')) {
                $table->foreignId('root_event_id')
                    ->nullable()
                    ->after('split_from_event_id')
                    ->constrained('events')
                    ->nullOnDelete();
            }
        });

        DB::statement("UPDATE events SET root_event_id = id WHERE root_event_id IS NULL AND recurrence_parent_id IS NULL");

        DB::table('events')
            ->whereNotNull('recurrence_parent_id')
            ->whereNull('root_event_id')
            ->orderBy('id')
            ->get(['id', 'recurrence_parent_id'])
            ->each(function (object $child): void {
                $parent = DB::table('events')
                    ->where('id', $child->recurrence_parent_id)
                    ->first(['id', 'root_event_id']);

                if (! $parent) {
                    return;
                }

                DB::table('events')
                    ->where('id', $child->id)
                    ->update([
                        'root_event_id' => $parent->root_event_id ?? $parent->id,
                    ]);
            });

        Schema::table('events', function (Blueprint $table): void {
            $table->index(['root_event_id'], 'events_root_event_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex('events_root_event_id_index');

            if (Schema::hasColumn('events', 'root_event_id')) {
                $table->dropConstrainedForeignId('root_event_id');
            }
        });
    }
};
