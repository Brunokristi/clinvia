<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_slot_id',
        'branch_id',
        'service_id',
        'patient_name',
        'patient_email',
        'patient_phone',
        'status',
        'patient_note',
        'admin_note',
    ];

    public function bookingSlot(): BelongsTo
    {
        return $this->belongsTo(BookingSlot::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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