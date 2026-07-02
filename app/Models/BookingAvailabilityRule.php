<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BookingAvailabilityRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'day_of_week',
        'date',
        'starts_at',
        'ends_at',
        'slot_mode',
        'service_id',
        'service_ids',
        'bookable_places',
        'repeats',
        'repeat_every',
        'repeat_unit',
        'repeat_weekdays',
        'repeat_ends_on',
        'excluded_dates',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'date' => 'date',
            'service_ids' => 'array',
            'service_id' => 'integer',
            'bookable_places' => 'integer',
            'repeats' => 'boolean',
            'repeat_every' => 'integer',
            'repeat_weekdays' => 'array',
            'repeat_ends_on' => 'date',
            'excluded_dates' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'booking_availability_rule_service')
            ->withTimestamps();
    }
}
