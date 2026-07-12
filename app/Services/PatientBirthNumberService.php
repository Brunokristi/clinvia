<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class PatientBirthNumberService
{
    public function normalize(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? '')) ?? '';

        if ($digits === '') {
            return null;
        }

        return $digits;
    }

    public function validationError(?string $normalized): ?string
    {
        if ($normalized === null) {
            return 'invalid_format';
        }

        $length = strlen($normalized);

        if (! in_array($length, [9, 10], true)) {
            return 'invalid_format';
        }

        $parsedDate = $this->parseDate($normalized);

        if ($parsedDate === null) {
            return 'invalid_date';
        }

        if ($length === 10) {
            $year = (int) $parsedDate->format('Y');
            $number = (int) $normalized;

            if ($year >= 1954) {
                $mod = $number % 11;

                if ($mod !== 0) {
                    // Historical exception used for some numbers issued before 1986.
                    if (! ($year < 1986 && $mod === 10)) {
                        return 'invalid_checksum';
                    }
                }
            }
        }

        return null;
    }

    public function isValid(?string $normalized): bool
    {
        return $this->validationError($normalized) === null;
    }

    public function parseDate(string $normalized): ?\DateTimeImmutable
    {
        if (strlen($normalized) < 6) {
            return null;
        }

        $yy = (int) substr($normalized, 0, 2);
        $mm = (int) substr($normalized, 2, 2);
        $dd = (int) substr($normalized, 4, 2);

        $realMonth = $this->resolveRealMonth($mm);

        if ($realMonth === null) {
            return null;
        }

        $year = $this->resolveYear($yy, strlen($normalized));

        if (! checkdate($realMonth, $dd, $year)) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-%d-%d', $year, $realMonth, $dd)) ?: null;
    }

    public function mask(?string $normalized): ?string
    {
        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (strlen($normalized) <= 4) {
            return str_repeat('*', strlen($normalized));
        }

        return str_repeat('*', max(0, strlen($normalized) - 4)) . substr($normalized, -4);
    }

    public function hash(?string $normalized): ?string
    {
        if ($normalized === null || $normalized === '') {
            return null;
        }

        $key = (string) config('patients.birth_number_hash_key');

        if ($key === '') {
            throw new \RuntimeException('Missing patients.birth_number_hash_key configuration.');
        }

        return hash_hmac('sha256', $normalized, $key);
    }

    public function encrypt(?string $normalized): ?string
    {
        if ($normalized === null || $normalized === '') {
            return null;
        }

        return Crypt::encryptString($normalized);
    }

    public function decrypt(?string $encrypted): ?string
    {
        if ($encrypted === null || $encrypted === '') {
            return null;
        }

        return Crypt::decryptString($encrypted);
    }

    private function resolveRealMonth(int $encodedMonth): ?int
    {
        foreach ([70, 50, 20, 0] as $offset) {
            $month = $encodedMonth - $offset;

            if ($month >= 1 && $month <= 12) {
                return $month;
            }
        }

        return null;
    }

    private function resolveYear(int $yy, int $length): int
    {
        if ($length === 9) {
            return 1900 + $yy;
        }

        $currentTwoDigitYear = (int) now()->format('y');

        return $yy <= $currentTwoDigitYear
            ? 2000 + $yy
            : 1900 + $yy;
    }
}
