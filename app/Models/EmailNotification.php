<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailNotification extends Model
{
    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'recipient_email',
        'notifiable_type',
        'notifiable_id',
        'root_event_id',
        'occurrence_display_key',
        'notification_type',
        'scope',
        'dedupe_key',
        'payload_hash',
        'sent_at',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
