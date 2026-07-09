<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentRequestAuditLog extends Model
{
    protected $fillable = [
        'appointment_request_id',
        'branch_id',
        'action',
        'reason',
        'payload',
        'performed_by',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function appointmentRequest(): BelongsTo
    {
        return $this->belongsTo(AppointmentRequest::class);
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
