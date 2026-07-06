<?php

namespace App\Modules\Calendar\Services;

use App\Modules\Calendar\Enums\EventAction;
use App\Modules\Calendar\Events\BranchEventUpdated;
use App\Modules\Calendar\Jobs\SendEventNotificationJob;
use App\Modules\Calendar\Models\Event;

class EventNotificationService
{
    public function dispatchMutationSignals(
        Event $event,
        EventAction $action,
        array $affectedEventIds = [],
        ?string $recurrenceScope = null,
        ?string $occurrenceStartsAt = null,
        ?array $recipientEmails = null,
    ): void {
        BranchEventUpdated::dispatch(
            branchId: $event->branch_id,
            eventId: $event->id,
            rootEventId: $event->recurrence_parent_id ?: $event->id,
            eventType: $event->type?->value,
            action: $action->value,
            affectedEventIds: $affectedEventIds,
            recurrenceScope: $recurrenceScope,
            occurrenceStartsAt: $occurrenceStartsAt,
            version: 1,
        );

        SendEventNotificationJob::dispatchSync(
            eventId: $event->id,
            action: $action->value,
            eventType: (string) $event->type?->value,
            recipientEmails: $recipientEmails,
        );
    }
}
