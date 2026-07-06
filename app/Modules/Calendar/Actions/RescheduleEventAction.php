<?php

namespace App\Modules\Calendar\Actions;

use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventMutationService;
use Carbon\Carbon;

class RescheduleEventAction
{
    public function __construct(
        private readonly EventMutationService $eventMutationService,
    ) {
    }

    public function execute(
        Event $event,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $actorId = null,
        ?string $scope = null,
        ?Carbon $occurrenceDate = null,
    ): Event {
        return $this->eventMutationService->reschedule(
            event: $event,
            startsAt: $startsAt,
            endsAt: $endsAt,
            actorId: $actorId,
            scope: $scope,
            occurrenceDate: $occurrenceDate,
        );
    }
}
