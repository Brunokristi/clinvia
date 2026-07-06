<?php

namespace App\Modules\Calendar\Actions;

use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventMutationService;

class UpdateEventAction
{
    public function __construct(
        private readonly EventMutationService $eventMutationService,
    ) {
    }

    public function execute(Event $event, array $payload, ?int $actorId = null, ?string $scope = null): Event
    {
        return $this->eventMutationService->update($event, $payload, $actorId, $scope);
    }
}
