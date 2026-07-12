<?php

namespace Tests\Unit\Patients;

use App\Services\PatientBirthNumberService;
use Tests\TestCase;

class PatientBirthNumberServiceTest extends TestCase
{
    public function test_normalizes_various_input_formats_to_digits_only(): void
    {
        $service = app(PatientBirthNumberService::class);

        $this->assertSame('9001011236', $service->normalize('900101/1236'));
        $this->assertSame('9001011236', $service->normalize('9001011236'));
        $this->assertSame('9001011236', $service->normalize('90 01 01 / 1236'));
    }

    public function test_validates_historic_nine_digit_birth_number(): void
    {
        $service = app(PatientBirthNumberService::class);

        $normalized = $service->normalize('530101123');

        $this->assertTrue($service->isValid($normalized));
    }

    public function test_validates_female_month_offset(): void
    {
        $service = app(PatientBirthNumberService::class);

        $normalized = $service->normalize('535101123');

        $this->assertTrue($service->isValid($normalized));
    }

    public function test_rejects_invalid_characters_and_invalid_format(): void
    {
        $service = app(PatientBirthNumberService::class);

        $this->assertSame('invalid_format', $service->validationError($service->normalize('ABC123')));
    }

    public function test_rejects_invalid_encoded_date(): void
    {
        $service = app(PatientBirthNumberService::class);

        $normalized = $service->normalize('9913321234');

        $this->assertSame('invalid_date', $service->validationError($normalized));
    }

    public function test_rejects_invalid_month_offset(): void
    {
        $service = app(PatientBirthNumberService::class);

        $normalized = $service->normalize('9963311234');

        $this->assertSame('invalid_date', $service->validationError($normalized));
    }

    public function test_validates_checksum_for_ten_digit_numbers_when_applicable(): void
    {
        $service = app(PatientBirthNumberService::class);

        $valid = null;
        $invalid = null;

        for ($i = 0; $i <= 9999; $i++) {
            $candidate = '900101' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $error = $service->validationError($candidate);

            if ($error === null && $valid === null) {
                $valid = $candidate;
            }

            if ($error === 'invalid_checksum' && $invalid === null) {
                $invalid = $candidate;
            }

            if ($valid !== null && $invalid !== null) {
                break;
            }
        }

        $this->assertNotNull($valid);
        $this->assertNotNull($invalid);
        $this->assertTrue($service->isValid($valid));
        $this->assertFalse($service->isValid($invalid));
    }
}
