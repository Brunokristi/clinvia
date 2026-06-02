<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'icon',
        'featured_image_path',
        'is_bookable',
        'duration_sessions',
        'duration_minutes',
        'capacity',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'booking_type',
        'insurance_amount',
        'insurance_note',
        'self_pay_amount',
        'self_pay_note',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_bookable' => 'boolean',
            'duration_sessions' => 'integer',
            'duration_minutes' => 'integer',
            'capacity' => 'integer',
            'buffer_before_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
            'insurance_amount' => 'decimal:2',
            'self_pay_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    public function information(): HasMany
    {
        return $this->hasMany(ServiceInformation::class)->orderBy('sort_order');
    }

    public function necessities(): HasMany
    {
        return $this->hasMany(ServiceNecessity::class)->orderBy('sort_order');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ServiceStep::class)->orderBy('sort_order');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(ServiceTag::class)->orderBy('sort_order');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ServiceFile::class)->orderBy('sort_order');
    }

    public function bookingAvailabilityRules(): BelongsToMany
    {
        return $this->belongsToMany(BookingAvailabilityRule::class, 'booking_availability_rule_service')
            ->withTimestamps();
    }

    public function bookingSlots(): HasMany
    {
        return $this->hasMany(BookingSlot::class);
    }
}