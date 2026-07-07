<?php

namespace App\Modules\Calendar\Models;

use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use App\Modules\Calendar\Enums\EventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Event extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'events';

    protected $fillable = [
        'branch_id',
        'type',
        'status',
        'starts_at',
        'ends_at',
        'timezone',
        'title',
        'description',
        'recurrence_rule',
        'recurrence_parent_id',
        'recurrence_exception_date',
        'recurrence_original_starts_at',
        'recurrence_original_ends_at',
        'split_from_event_id',
        'root_event_id',
        'recurrence_sequence',
        'is_recurring',
        'metadata',
        'created_by',
        'updated_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'recurrence_rule' => 'array',
            'recurrence_exception_date' => 'date',
            'recurrence_original_starts_at' => 'datetime',
            'recurrence_original_ends_at' => 'datetime',
            'recurrence_sequence' => 'integer',
            'root_event_id' => 'integer',
            'is_recurring' => 'boolean',
            'metadata' => 'array',
            'cancelled_at' => 'datetime',
            'type' => EventType::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function bookingDetail(): HasOne
    {
        return $this->hasOne(BookingEventDetail::class);
    }

    public function availabilityRuleDetail(): HasOne
    {
        return $this->hasOne(AvailabilityRuleEventDetail::class);
    }

    public function groupDetail(): HasOne
    {
        return $this->hasOne(GroupEventDetail::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'event_service')
            ->withPivot([
                'duration_minutes_snapshot',
                'price_snapshot',
                'sort_order',
                'quantity',
            ])
            ->withTimestamps();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(GroupEventParticipant::class);
    }

    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    public function recurrenceChildren(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_parent_id');
    }

    public function splitFromEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'split_from_event_id');
    }

    public function rootEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_event_id');
    }

    public function logicalSegments(): HasMany
    {
        return $this->hasMany(self::class, 'root_event_id');
    }

    public function splitEvents(): HasMany
    {
        return $this->hasMany(self::class, 'split_from_event_id');
    }

    protected static function booted(): void
    {
        static::created(function (self $event): void {
            if (! Schema::hasColumn('events', 'root_event_id') || $event->root_event_id !== null) {
                return;
            }

            $rootEventId = null;

            if ($event->recurrence_parent_id !== null) {
                $parent = self::query()->find($event->recurrence_parent_id);
                $rootEventId = $parent?->root_event_id ?? $parent?->id;
            } else {
                $rootEventId = $event->id;
            }

            if ($rootEventId !== null) {
                $event->root_event_id = $rootEventId;
                $event->saveQuietly();
            }
        });
    }

    public function getDisplayTitleAttribute(): string
    {
        if (filled($this->title)) {
            return (string) $this->title;
        }

        return match ($this->type) {
            EventType::Booking => $this->bookingDetail?->patient_name
                ? 'Rezervacia: ' . $this->bookingDetail->patient_name
                : 'Rezervacia',
            EventType::AvailabilityRule => 'Pravidlo dostupnosti',
            EventType::GroupEvent => $this->groupDetail?->service_name
                ? 'Skupina: ' . $this->groupDetail->service_name
                : 'Skupinovy termin',
            default => 'Udalost',
        };
    }
}
