<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PatientDirectoryService
{
    public function __construct(
        private readonly PatientBirthNumberService $birthNumberService,
    ) {
    }

    public function savePatient(
        Branch $branch,
        ?string $name,
        ?string $email = null,
        ?string $phone = null,
        ?string $birthNumber = null,
    ): ?Patient
    {
        if (! Schema::hasTable('patients')) {
            return null;
        }

        $normalizedName = $this->normalizeValue($name);

        if (blank($normalizedName)) {
            return null;
        }

        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedPhone = $this->normalizePhone($phone);
        $normalizedBirthNumber = $this->birthNumberService->normalize($birthNumber);

        $patient = $this->findExistingPatient(
            $branch,
            $normalizedName,
            $normalizedEmail,
            $normalizedPhone,
            $normalizedBirthNumber,
        )
            ?? new Patient(['branch_id' => $branch->id]);

        $patient->patient_name = $normalizedName;

        if (filled($normalizedEmail)) {
            $patient->patient_email = $normalizedEmail;
        }

        if (filled($normalizedPhone)) {
            $patient->patient_phone = $normalizedPhone;
        }

        if (filled($normalizedBirthNumber) && $this->birthNumberService->isValid($normalizedBirthNumber)) {
            $patient->patient_birth_number = $normalizedBirthNumber;
        }

        $patient->last_used_at = now();

        try {
            $patient->save();
        } catch (QueryException $exception) {
            if (! $this->isBirthNumberHashUniqueViolation($exception) || ! filled($normalizedBirthNumber)) {
                throw $exception;
            }

            $existingByHash = Patient::query()
                ->where('birth_number_hash', $this->birthNumberService->hash($normalizedBirthNumber))
                ->first();

            if (! $existingByHash) {
                throw $exception;
            }

            $existingByHash->last_used_at = now();
            $existingByHash->save();

            return $existingByHash;
        }

        return $patient;
    }

    public function getBranchPatientsForAutocomplete(Branch $branch, int $limit = 200): Collection
    {
        if (! Schema::hasTable('patients')) {
            return collect();
        }

        return Patient::query()
            ->where('branch_id', $branch->id)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'patient_name', 'patient_email', 'patient_phone', 'patient_birth_number'])
            ->map(function (Patient $patient): array {
                $normalizedBirthNumber = $this->birthNumberService->normalize($patient->patient_birth_number);

                return [
                    'id' => $patient->id,
                    'patient_name' => $patient->patient_name,
                    'patient_email' => $patient->patient_email,
                    'patient_phone' => $patient->patient_phone,
                    'patient_birth_number' => $this->birthNumberService->mask($normalizedBirthNumber),
                ];
            })
            ->values();
    }

    private function findExistingPatient(
        Branch $branch,
        string $name,
        ?string $email,
        ?string $phone,
        ?string $birthNumber,
    ): ?Patient {
        if (filled($birthNumber) && $this->birthNumberService->isValid($birthNumber)) {
            $hash = $this->birthNumberService->hash($birthNumber);

            $byBirthNumber = Patient::query()
                ->where('birth_number_hash', $hash)
                ->first();

            if ($byBirthNumber) {
                return $byBirthNumber;
            }
        }

        if (filled($email)) {
            $byEmail = Patient::query()
                ->where('branch_id', $branch->id)
                ->whereRaw('LOWER(patient_email) = ?', [strtolower($email)])
                ->first();

            if ($byEmail) {
                return $byEmail;
            }
        }

        if (filled($phone)) {
            $byPhone = Patient::query()
                ->where('branch_id', $branch->id)
                ->where('patient_phone', $phone)
                ->first();

            if ($byPhone) {
                return $byPhone;
            }
        }

        return Patient::query()
            ->where('branch_id', $branch->id)
            ->where('patient_name', $name)
            ->first();
    }

    private function normalizeValue(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeEmail(?string $email): ?string
    {
        $normalized = $this->normalizeValue($email);

        return $normalized ? strtolower($normalized) : null;
    }

    private function normalizePhone(?string $phone): ?string
    {
        return $this->normalizeValue($phone);
    }

    private function isBirthNumberHashUniqueViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'patients_birth_number_hash_unique')
            || str_contains($message, 'birth_number_hash');
    }
}
