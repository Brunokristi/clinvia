<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BranchInboxUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $branchId,
        public ?int $messageId = null,
        public string $action = 'updated',
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('branches.' . $this->branchId . '.inbox');
    }

    public function broadcastAs(): string
    {
        return 'inbox.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'branch_id' => $this->branchId,
            'message_id' => $this->messageId,
            'action' => $this->action,
        ];
    }
}