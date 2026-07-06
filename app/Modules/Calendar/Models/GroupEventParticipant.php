<?php

namespace App\Modules\Calendar\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupEventParticipant extends Model
{
    use HasFactory;

    protected $table = 'group_event_participants';

    protected $fillable = [
        'event_id',
        'patient_id',
        'status',
        'booked_at',
        'cancelled_at',
        'notes',
        'participant_name',
        'participant_email',
        'participant_phone',
    ];

    protected function casts(): array
    {
        return [
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
