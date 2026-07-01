<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('capacity_windows', 'series_uuid')) {
            DB::statement('ALTER TABLE capacity_windows ADD COLUMN series_uuid UUID NULL');
        }

        if (! Schema::hasColumn('capacity_windows', 'admin_note')) {
            DB::statement('ALTER TABLE capacity_windows ADD COLUMN admin_note TEXT NULL');
        }

        DB::table('capacity_windows')
            ->whereNull('series_uuid')
            ->orderBy('id')
            ->chunkById(100, function ($windows): void {
                foreach ($windows as $window) {
                    DB::table('capacity_windows')
                        ->where('id', $window->id)
                        ->update([
                            'series_uuid' => (string) Str::uuid(),
                        ]);
                }
            });

        $this->createIndexIfMissing(
            'capacity_windows',
            'capacity_windows_branch_id_series_uuid_index',
            'CREATE INDEX capacity_windows_branch_id_series_uuid_index ON capacity_windows (branch_id, series_uuid)'
        );

        $this->dropForeignKeyIfExists(
            table: 'capacity_windows',
            constraint: 'capacity_windows_booking_availability_rule_id_foreign',
        );

        if (DB::getDriverName() !== 'sqlite' && Schema::hasColumn('capacity_windows', 'booking_availability_rule_id')) {
            DB::statement('ALTER TABLE capacity_windows DROP COLUMN booking_availability_rule_id');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('capacity_windows', 'booking_availability_rule_id')) {
            DB::statement('ALTER TABLE capacity_windows ADD COLUMN booking_availability_rule_id BIGINT NULL');

            DB::statement('
                ALTER TABLE capacity_windows
                ADD CONSTRAINT capacity_windows_booking_availability_rule_id_foreign
                FOREIGN KEY (booking_availability_rule_id)
                REFERENCES booking_availability_rules(id)
                ON DELETE SET NULL
            ');
        }

        $this->dropIndexIfExists(
            'capacity_windows',
            'capacity_windows_branch_id_series_uuid_index',
        );

        if (Schema::hasColumn('capacity_windows', 'admin_note')) {
            DB::statement('ALTER TABLE capacity_windows DROP COLUMN admin_note');
        }

        if (Schema::hasColumn('capacity_windows', 'series_uuid')) {
            DB::statement('ALTER TABLE capacity_windows DROP COLUMN series_uuid');
        }
    }

    private function createIndexIfMissing(string $table, string $indexName, string $sql): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $exists = DB::table('pg_indexes')
            ->where('tablename', $table)
            ->where('indexname', $indexName)
            ->exists();

        if (! $exists) {
            DB::statement($sql);
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $exists = DB::table('pg_indexes')
            ->where('tablename', $table)
            ->where('indexname', $indexName)
            ->exists();

        if ($exists) {
            DB::statement("DROP INDEX {$indexName}");
        }
    }

    private function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $exists = DB::table('pg_constraint')
            ->join('pg_class', 'pg_constraint.conrelid', '=', 'pg_class.oid')
            ->where('pg_class.relname', $table)
            ->where('pg_constraint.conname', $constraint)
            ->exists();

        if ($exists) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
        }
    }
};