<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Patient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PatientDirectoryService
{
    public function savePatient(Branch $branch, ?string $name, ?string $email = null, ?string $phone = null): ?Patient
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

        $patient = $this->findExistingPatient($branch, $normalizedName, $normalizedEmail, $normalizedPhone)
            ?? new Patient(['branch_id' => $branch->id]);

        $patient->patient_name = $normalizedName;

        if (filled($normalizedEmail)) {
            $patient->patient_email = $normalizedEmail;
        }

        if (filled($normalizedPhone)) {
            $patient->patient_phone = $normalizedPhone;
        }

        $patient->last_used_at = now();
        $patient->save();

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
            ->get(['id', 'patient_name', 'patient_email', 'patient_phone'])
            ->values();
    }

    private function findExistingPatient(
        Branch $branch,
        string $name,
        ?string $email,
        ?string $phone,
    ): ?Patient {
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
}
