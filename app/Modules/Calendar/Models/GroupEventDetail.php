<?php

namespace App\Modules\Calendar\Models;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupEventDetail extends Model
{
    use HasFactory;

    protected $table = 'group_event_details';

    protected $fillable = [
        'event_id',
        'service_id',
        'service_name',
        'capacity',
        'reserved_places',
        'group_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'reserved_places' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getAvailablePlacesAttribute(): int
    {
        return max(0, (int) $this->capacity - (int) $this->reserved_places);
    }
}
