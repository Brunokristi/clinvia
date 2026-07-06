<?php

namespace App\Modules\Calendar\Actions;

use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventMutationService;

class UpdateEventServicesAction
{
    public function __construct(
        private readonly EventMutationService $eventMutationService,
    ) {
    }

    public function execute(Event $event, array $services, ?int $actorId = null): Event
    {
        return $this->eventMutationService->updateServices($event, $services, $actorId);
    }
}
