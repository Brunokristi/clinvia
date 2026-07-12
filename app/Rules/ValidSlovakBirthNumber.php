<?php

namespace App\Rules;

use App\Services\PatientBirthNumberService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidSlovakBirthNumber implements ValidationRule
{
    public function __construct(
        private readonly bool $required = false,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $service = app(PatientBirthNumberService::class);
        $normalized = $service->normalize($value);

        if ($normalized === null) {
            if ($this->required) {
                $fail('Rodné číslo je povinné.');
            }

            return;
        }

        $error = $service->validationError($normalized);

        if ($error === null) {
            return;
        }

        $fail(match ($error) {
            'invalid_format' => 'Rodné číslo nemá platný formát.',
            'invalid_date' => 'Rodné číslo obsahuje neplatný dátum.',
            'invalid_checksum' => 'Rodné číslo nemá platný formát.',
            default => 'Rodné číslo nemá platný formát.',
        });
    }
}
