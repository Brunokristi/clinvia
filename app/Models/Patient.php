<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'patient_name',
        'patient_email',
        'patient_phone',
        'patient_birth_number',
        'birth_number_encrypted',
        'birth_number_hash',
        'last_used_at',
    ];

    protected $hidden = [
        'birth_number_encrypted',
        'birth_number_hash',
    ];

    protected function casts(): array
    {
        return [
            'birth_number_encrypted' => 'encrypted',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Patient $patient): void {
            $birthNumberService = app(\App\Services\PatientBirthNumberService::class);
            $normalized = $birthNumberService->normalize($patient->patient_birth_number);

            if (! $birthNumberService->isValid($normalized)) {
                $patient->birth_number_encrypted = null;
                $patient->birth_number_hash = null;

                return;
            }

            $patient->patient_birth_number = $normalized;
            $patient->birth_number_encrypted = $normalized;
            $patient->birth_number_hash = $birthNumberService->hash($normalized);
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
