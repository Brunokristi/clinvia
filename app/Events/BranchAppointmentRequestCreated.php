<?php

namespace App\Events;

use App\Models\AppointmentRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BranchAppointmentRequestCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly AppointmentRequest $appointmentRequest)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('branch.' . $this->appointmentRequest->branch_id);
    }

    public function broadcastWith(): array
    {
        return [
            'appointment_request_id' => $this->appointmentRequest->id,
            'branch_id' => $this->appointmentRequest->branch_id,
        ];
    }

    public function broadcastAs(): string
    {
        return 'branch.appointment-request.created';
    }
}
