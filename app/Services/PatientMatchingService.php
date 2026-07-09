<?php

namespace App\Services;

use App\Models\AppointmentRequest;
use App\Models\Patient;
use Illuminate\Support\Collection;

class PatientMatchingService
{
    public function findMatchesForRequest(AppointmentRequest $request, int $limit = 10): Collection
    {
        $request->loadMissing('branch');

        $email = $this->normalizeEmail($request->patient_email);
        $phone = $this->normalizePhone($request->patient_phone);
        $firstName = $this->normalizeNamePart($request->first_name ?: $this->extractFirstName($request->patient_name));
        $lastName = $this->normalizeNamePart($request->last_name ?: $this->extractLastName($request->patient_name));
        $birthDate = $request->date_of_birth?->toDateString() ?: null;

        $patients = Patient::query()
            ->where('branch_id', $request->branch_id)
            ->limit(500)
            ->get();

        $matches = $patients
            ->map(function (Patient $patient) use ($email, $phone, $firstName, $lastName, $birthDate): ?array {
                $patientEmail = $this->normalizeEmail($patient->patient_email);
                $patientPhone = $this->normalizePhone($patient->patient_phone);
                $patientFirstName = $this->normalizeNamePart($this->extractFirstName($patient->patient_name));
                $patientLastName = $this->normalizeNamePart($this->extractLastName($patient->patient_name));
                $patientBirthDate = $this->extractBirthDate($patient);

                $confidence = null;
                $score = 0;

                if ($email && $patientEmail && $email === $patientEmail) {
                    $confidence = 'exact_email';
                    $score = 100;
                } elseif ($phone && $patientPhone && $phone === $patientPhone) {
                    $confidence = 'exact_phone';
                    $score = 95;
                } elseif ($firstName && $lastName && $birthDate && $patientBirthDate
                    && $firstName === $patientFirstName
                    && $lastName === $patientLastName
                    && $birthDate === $patientBirthDate) {
                    $confidence = 'name_and_birth_date';
                    $score = 90;
                } elseif ($firstName && $lastName && $email && $patientEmail
                    && $firstName === $patientFirstName
                    && $lastName === $patientLastName
                    && $email === $patientEmail) {
                    $confidence = 'name_and_email';
                    $score = 85;
                } elseif ($firstName && $lastName && $phone && $patientPhone
                    && $firstName === $patientFirstName
                    && $lastName === $patientLastName
                    && $phone === $patientPhone) {
                    $confidence = 'name_and_phone';
                    $score = 80;
                } elseif ($firstName && $lastName && $this->isPossibleNameMatch($firstName, $lastName, $patientFirstName, $patientLastName)) {
                    $confidence = 'possible_name_match';
                    $score = 50;
                }

                if (! $confidence) {
                    return null;
                }

                return [
                    'patient_id' => $patient->id,
                    'confidence' => $confidence,
                    'score' => $score,
                    'patient' => [
                        'id' => $patient->id,
                        'patient_name' => $patient->patient_name,
                        'patient_email' => $patient->patient_email,
                        'patient_phone' => $patient->patient_phone,
                        'patient_birth_number' => $patient->patient_birth_number,
                    ],
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->values();

        return $matches->take($limit);
    }

    public function hasStrongMatch(AppointmentRequest $request): bool
    {
        return $this->findMatchesForRequest($request)
            ->contains(fn (array $match): bool => in_array($match['confidence'], [
                'exact_email',
                'exact_phone',
                'name_and_birth_date',
                'name_and_email',
                'name_and_phone',
            ], true));
    }

    public function normalizeEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }

    public function normalizePhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        $normalized = preg_replace('/[\s\-\/]/', '', $phone) ?? '';

        if (str_starts_with($normalized, '00421')) {
            $normalized = '+421' . substr($normalized, 5);
        } elseif (str_starts_with($normalized, '421') && ! str_starts_with($normalized, '+421')) {
            $normalized = '+'.$normalized;
        } elseif (preg_match('/^0\d{9,10}$/', $normalized) === 1) {
            $normalized = '+421' . ltrim($normalized, '0');
        }

        return $normalized;
    }

    private function normalizeNamePart(?string $value): ?string
    {
        $value = trim(mb_strtolower((string) $value));

        return $value !== '' ? $value : null;
    }

    private function extractFirstName(?string $fullName): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $fullName)) ?: [];

        return $parts[0] ?? null;
    }

    private function extractLastName(?string $fullName): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $fullName)) ?: [];

        return count($parts) > 1 ? $parts[count($parts) - 1] : null;
    }

    private function extractBirthDate(Patient $patient): ?string
    {
        $birthNumber = trim((string) ($patient->patient_birth_number ?? ''));

        if ($birthNumber === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $birthNumber) ?? '';

        if (strlen($digits) < 6) {
            return null;
        }

        $yy = (int) substr($digits, 0, 2);
        $mm = (int) substr($digits, 2, 2);
        $dd = (int) substr($digits, 4, 2);

        if ($mm > 70) {
            $mm -= 70;
        } elseif ($mm > 50) {
            $mm -= 50;
        } elseif ($mm > 20) {
            $mm -= 20;
        }

        $century = strlen($digits) >= 10 ? 1900 : 2000;

        if ($yy <= (int) now()->format('y')) {
            $century = 2000;
        }

        $year = $century + $yy;

        if (! checkdate($mm, $dd, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $mm, $dd);
    }

    private function isPossibleNameMatch(?string $firstName, ?string $lastName, ?string $patientFirstName, ?string $patientLastName): bool
    {
        if (! $firstName || ! $lastName || ! $patientFirstName || ! $patientLastName) {
            return false;
        }

        similar_text($firstName.' '.$lastName, $patientFirstName.' '.$patientLastName, $percent);

        return $percent >= 80;
    }
}
