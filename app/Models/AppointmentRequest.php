<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AppointmentRequest extends Model
{
    protected $fillable = [
        'branch_id',
        'booking_id',
        'preferred_date',
        'preferred_period',
        'total_duration_minutes',
        'patient_name',
        'patient_email',
        'patient_phone',
        'patient_note',
        'status',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'total_duration_minutes' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
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
}