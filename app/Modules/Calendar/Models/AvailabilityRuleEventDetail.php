<?php

namespace App\Modules\Calendar\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityRuleEventDetail extends Model
{
    use HasFactory;

    protected $table = 'availability_rule_event_details';

    protected $fillable = [
        'event_id',
        'capacity_rules',
        'visibility_rules',
        'min_booking_notice_minutes',
        'max_booking_notice_minutes',
        'slot_interval_minutes',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'online_booking_rules',
    ];

    protected function casts(): array
    {
        return [
            'capacity_rules' => 'array',
            'visibility_rules' => 'array',
            'online_booking_rules' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
