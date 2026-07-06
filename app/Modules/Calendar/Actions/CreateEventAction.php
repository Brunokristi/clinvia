<?php

namespace App\Modules\Calendar\Actions;

use App\Models\Branch;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventMutationService;

class CreateEventAction
{
    public function __construct(
        private readonly EventMutationService $eventMutationService,
    ) {
    }

    public function execute(Branch $branch, array $payload, ?int $actorId = null): Event
    {
        return $this->eventMutationService->create($branch, $payload, $actorId);
    }
}
