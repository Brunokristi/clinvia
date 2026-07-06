<?php

namespace App\Modules\Calendar\Actions;

use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventMutationService;

class CancelEventAction
{
    public function __construct(
        private readonly EventMutationService $eventMutationService,
    ) {
    }

    public function execute(Event $event, ?int $actorId = null, ?string $scope = null): Event
    {
        return $this->eventMutationService->cancel($event, $actorId, $scope);
    }
}
