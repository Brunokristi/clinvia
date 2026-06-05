<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BranchBookingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Booking $booking)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('branch.' . $this->booking->branch_id);
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'branch_id' => $this->booking->branch_id,
        ];
    }

    public function broadcastAs(): string
    {
        return 'branch.booking.created';
    }
}
