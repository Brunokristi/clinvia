<?php

namespace App\Modules\Calendar\Actions;

use App\Models\AppointmentRequest;
use App\Models\Branch;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventMutationService;

class ConvertAppointmentRequestToEventAction
{
    public function __construct(
        private readonly EventMutationService $eventMutationService,
    ) {
    }

    public function execute(Branch $branch, AppointmentRequest $appointmentRequest, array $payload, ?int $actorId = null): Event
    {
        return $this->eventMutationService->convertAppointmentRequest($branch, $appointmentRequest, $payload, $actorId);
    }
}
