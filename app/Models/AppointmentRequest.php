<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentRequest extends Model
{
    protected $fillable = [
        'branch_id',
        'booking_id',
        'first_name',
        'last_name',
        'preferred_date',
        'preferred_period',
        'preferred_starts_at',
        'preferred_time_note',
        'total_duration_minutes',
        'patient_name',
        'patient_email',
        'normalized_email',
        'patient_phone',
        'normalized_phone',
        'patient_birth_number',
        'date_of_birth',
        'patient_note',
        'privacy_consent_accepted_at',
        'status',
        'request_type',
        'verification_token_hash',
        'verification_expires_at',
        'email_verified_at',
        'patient_id',
        'accepted_booking_id',
        'rejected_reason',
        'manually_verified_at',
        'manually_verified_by',
        'manual_verification_reason',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'preferred_starts_at' => 'datetime',
        'date_of_birth' => 'date',
        'privacy_consent_accepted_at' => 'datetime',
        'verification_expires_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'manually_verified_at' => 'datetime',
        'total_duration_minutes' => 'integer',
    ];

    public const STATUS_PENDING_EMAIL_VERIFICATION = 'pending_email_verification';
    public const STATUS_PENDING_ADMIN_REVIEW = 'pending_admin_review';
    public const STATUS_MANUALLY_VERIFIED = 'manually_verified';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function acceptedBookingEvent(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Calendar\Models\Event::class, 'accepted_booking_id');
    }

    public function manuallyVerifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manually_verified_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AppointmentRequestAuditLog::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->withPivot([
                'duration_minutes_snapshot',
                'price_snapshot',
            ])
            ->withTimestamps();
    }

    public function isEmailVerifiedOrManuallyVerified(): bool
    {
        return $this->email_verified_at !== null || $this->manually_verified_at !== null;
    }
}