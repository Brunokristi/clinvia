<?php

namespace App\Modules\Calendar\Actions;

use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventMutationService;
use Carbon\Carbon;

class ResizeEventAction
{
    public function __construct(
        private readonly EventMutationService $eventMutationService,
    ) {
    }

    public function execute(Event $event, Carbon $endsAt, ?int $actorId = null, ?string $scope = null): Event
    {
        return $this->eventMutationService->resize($event, $endsAt, $actorId, $scope);
    }
}
