<?php

namespace App\Modules\Calendar\Actions;

use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Models\GroupEventParticipant;
use App\Modules\Calendar\Services\EventMutationService;

class AddGroupEventParticipantAction
{
    public function __construct(
        private readonly EventMutationService $eventMutationService,
    ) {
    }

    public function execute(Event $event, array $payload): GroupEventParticipant
    {
        return $this->eventMutationService->addGroupParticipant($event, $payload);
    }
}
