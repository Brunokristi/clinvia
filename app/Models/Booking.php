<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_slot_id',
        'branch_id',
        'service_id',
        'capacity_window_id',
        'series_uuid',
        'starts_at',
        'ends_at',
        'patient_name',
        'patient_email',
        'patient_phone',
        'status',
        'patient_note',
        'admin_note',
        'recurrence',
        'recurrence_excluded_dates',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'recurrence' => 'array',
            'recurrence_excluded_dates' => 'array',
        ];
    }

    public function bookingSlot(): BelongsTo
    {
        return $this->belongsTo(BookingSlot::class);
    }

    public function capacityWindow(): BelongsTo
    {
        return $this->belongsTo(CapacityWindow::class);
    }

    public function appointmentRequest(): HasOne
    {
        return $this->hasOne(AppointmentRequest::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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

    public function hasPatientEmail(): bool
    {
        return filled($this->patient_email);
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, ['cancelled', 'rejected', 'no_show'], true);
    }
}