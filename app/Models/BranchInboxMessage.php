<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchInboxMessage extends Model
{
    protected $fillable = [
        'branch_id',
        'type',
        'title',
        'body',
        'sender_name',
        'sender_email',
        'sender_phone',
        'booking_id',
        'appointment_request_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function appointmentRequest(): BelongsTo
    {
        return $this->belongsTo(AppointmentRequest::class);
    }

    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->update([
                'read_at' => now(),
            ]);
        }
    }

    public function markAsUnread(): void
    {
        if ($this->read_at) {
            $this->update([
                'read_at' => null,
            ]);
        }
    }
}