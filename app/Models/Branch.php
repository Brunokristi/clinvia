<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'type',
        'description',
        'address_line_1',
        'address_line_2',
        'city',
        'postal_code',
        'region',
        'country',
        'latitude',
        'longitude',
        'website',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class)->orderBy('sort_order');
    }

    public function openingHours(): HasMany
    {
        return $this->hasMany(OpeningHour::class)->orderBy('day_of_week');
    }

    public function userBranches(): HasMany
    {
        return $this->hasMany(UserBranch::class);
    }

    public function branchInvitations(): HasMany
    {
        return $this->hasMany(BranchInvitation::class)
            ->whereNull('accepted_at')
            ->latest();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branches')
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'branch_employees')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps()
            ->orderBy('branch_employees.sort_order');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('sort_order');
    }

    public function bookingAvailabilityRules(): HasMany
    {
        return $this->hasMany(BookingAvailabilityRule::class);
    }

    public function bookingSlots(): HasMany
    {
        return $this->hasMany(BookingSlot::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function branchInboxMessages(): HasMany
    {
        return $this->hasMany(BranchInboxMessage::class);
    }

    public function publicSite(): HasOne
    {
        return $this->hasOne(BranchPublicSite::class);
    }

    public function appointmentRequests(): HasMany
    {
        return $this->hasMany(AppointmentRequest::class);
    }

    public function inboxMessages(): HasMany
    {
        return $this->hasMany(BranchInboxMessage::class);
    }
}