<?php

namespace App\Modules\Calendar\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingEventDetail extends Model
{
    use HasFactory;

    protected $table = 'booking_event_details';

    protected $fillable = [
        'event_id',
        'patient_id',
        'source_request_id',
        'booking_source',
        'booking_status',
        'internal_notes',
        'public_notes',
        'patient_name',
        'patient_email',
        'patient_phone',
        'patient_birth_number',
        'contact_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'contact_snapshot' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
