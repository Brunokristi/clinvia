<?php

namespace App\Modules\Calendar\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BranchEventUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $branchId,
        public int $eventId,
        public ?int $rootEventId,
        public ?string $eventType,
        public string $action,
        public array $affectedEventIds = [],
        public ?string $recurrenceScope = null,
        public ?string $occurrenceStartsAt = null,
        public int $version = 1,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('branches.' . $this->branchId . '.events');
    }

    public function broadcastAs(): string
    {
        return 'event.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'version' => $this->version,
            'branch_id' => $this->branchId,
            'event_id' => $this->eventId,
            'root_event_id' => $this->rootEventId,
            'event_type' => $this->eventType,
            'action' => $this->action,
            'scope' => $this->recurrenceScope,
            'occurrence_starts_at' => $this->occurrenceStartsAt,
            'affected_event_ids' => $this->affectedEventIds,
            'recurrence_scope' => $this->recurrenceScope,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
