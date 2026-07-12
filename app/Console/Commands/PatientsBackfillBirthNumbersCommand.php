<?php

namespace App\Console\Commands;

use App\Models\AppointmentRequest;
use App\Models\Patient;
use App\Services\PatientBirthNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class PatientsBackfillBirthNumbersCommand extends Command
{
    protected $signature = 'patients:backfill-birth-numbers {--fail-on-duplicates : Return non-zero exit code when duplicates are found}';

    protected $description = 'Backfill encrypted/hash birth-number fields for patients and appointment request snapshots';

    public function handle(PatientBirthNumberService $birthNumberService): int
    {
        $this->info('Backfilling patients.birth_number_* fields...');

        $updatedPatients = 0;
        $skippedInvalidPatients = 0;

        Patient::query()
            ->whereNotNull('patient_birth_number')
            ->orderBy('id')
            ->chunkById(200, function (Collection $patients) use ($birthNumberService, &$updatedPatients, &$skippedInvalidPatients): void {
                foreach ($patients as $patient) {
                    $normalized = $birthNumberService->normalize($patient->patient_birth_number);

                    if (! $birthNumberService->isValid($normalized)) {
                        $skippedInvalidPatients++;
                        continue;
                    }

                    $hash = $birthNumberService->hash($normalized);

                    if ($patient->birth_number_hash === $hash && filled($patient->birth_number_encrypted)) {
                        continue;
                    }

                    $patient->birth_number_encrypted = $normalized;
                    $patient->birth_number_hash = $hash;
                    $patient->save();

                    $updatedPatients++;
                }
            });

        $this->info('Backfilling appointment_requests.submitted_birth_number_* fields...');

        $updatedRequests = 0;

        AppointmentRequest::query()
            ->where(function ($query): void {
                $query->whereNotNull('patient_birth_number')
                    ->orWhereNotNull('submitted_birth_number_hash');
            })
            ->orderBy('id')
            ->chunkById(200, function (Collection $requests) use ($birthNumberService, &$updatedRequests): void {
                foreach ($requests as $request) {
                    $sourceValue = $request->patient_birth_number;
                    $normalized = $birthNumberService->normalize($sourceValue);

                    if (! $birthNumberService->isValid($normalized)) {
                        continue;
                    }

                    $hash = $birthNumberService->hash($normalized);

                    if ($request->submitted_birth_number_hash === $hash && filled($request->submitted_birth_number_encrypted)) {
                        continue;
                    }

                    $request->submitted_birth_number_encrypted = $normalized;
                    $request->submitted_birth_number_hash = $hash;
                    $request->save();

                    $updatedRequests++;
                }
            });

        $duplicates = Patient::query()
            ->selectRaw('birth_number_hash, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids')
            ->whereNotNull('birth_number_hash')
            ->groupBy('birth_number_hash')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->line('');
        $this->line('Summary:');
        $this->line("- Updated patients: {$updatedPatients}");
        $this->line("- Updated requests: {$updatedRequests}");
        $this->line("- Skipped invalid patient birth numbers: {$skippedInvalidPatients}");

        if ($duplicates->isNotEmpty()) {
            $this->warn('Duplicate birth-number hashes detected.');

            foreach ($duplicates as $duplicate) {
                $this->line(sprintf('  - hash:%s ids:[%s]', substr((string) $duplicate->birth_number_hash, 0, 12), (string) $duplicate->ids));
            }

            return $this->option('fail-on-duplicates') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('No duplicate birth-number hashes detected.');

        return self::SUCCESS;
    }
}
