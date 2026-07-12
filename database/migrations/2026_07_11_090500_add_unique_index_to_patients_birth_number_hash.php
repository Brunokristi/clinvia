<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patients') || ! Schema::hasColumn('patients', 'birth_number_hash')) {
            return;
        }

        $duplicateHashes = DB::table('patients')
            ->select('birth_number_hash', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('birth_number_hash')
            ->groupBy('birth_number_hash')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateHashes->isNotEmpty()) {
            $duplicateIdsByHash = DB::table('patients')
                ->select('id', 'birth_number_hash')
                ->whereIn('birth_number_hash', $duplicateHashes->pluck('birth_number_hash'))
                ->orderBy('birth_number_hash')
                ->orderBy('id')
                ->get()
                ->groupBy('birth_number_hash')
                ->map(fn ($rows) => $rows->pluck('id')->implode(','));

            $details = $duplicateHashes
                ->map(fn ($row) => sprintf(
                    '%s -> [%s]',
                    substr((string) $row->birth_number_hash, 0, 12),
                    (string) ($duplicateIdsByHash->get($row->birth_number_hash) ?? '')
                ))
                ->implode('; ');

            throw new \RuntimeException('Cannot add unique index on patients.birth_number_hash. Resolve duplicates first: '.$details);
        }

        Schema::table('patients', function ($table): void {
            $table->unique('birth_number_hash', 'patients_birth_number_hash_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('patients')) {
            return;
        }

        if (Schema::hasColumn('patients', 'birth_number_hash')) {
            Schema::table('patients', function ($table): void {
                $table->dropUnique('patients_birth_number_hash_unique');
            });
        }
    }
};
