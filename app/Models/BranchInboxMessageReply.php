<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchInboxMessageReply extends Model
{
    protected $fillable = [
        'branch_inbox_message_id',
        'subject',
        'body',
        'recipient_email',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(BranchInboxMessage::class, 'branch_inbox_message_id');
    }
}