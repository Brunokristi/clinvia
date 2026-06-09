<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BranchCalendarUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $branchId,
        public string $action = 'updated',
        public ?int $bookingId = null,
        public ?int $appointmentRequestId = null,
        public ?int $capacityWindowId = null,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('branches.' . $this->branchId . '.calendar');
    }

    public function broadcastAs(): string
    {
        return 'calendar.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'branch_id' => $this->branchId,
            'action' => $this->action,
            'booking_id' => $this->bookingId,
            'appointment_request_id' => $this->appointmentRequestId,
            'capacity_window_id' => $this->capacityWindowId,
        ];
    }
}