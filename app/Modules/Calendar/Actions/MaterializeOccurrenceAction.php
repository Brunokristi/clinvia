<?php

namespace App\Modules\Calendar\Actions;

use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventMutationService;
use Carbon\Carbon;

class MaterializeOccurrenceAction
{
    public function __construct(
        private readonly EventMutationService $eventMutationService,
    ) {
    }

    public function execute(Event $event, Carbon $occurrenceStartsAt, ?Carbon $occurrenceEndsAt = null, ?int $actorId = null): Event
    {
        return $this->eventMutationService->materializeOccurrence($event, $occurrenceStartsAt, $occurrenceEndsAt, $actorId);
    }
}