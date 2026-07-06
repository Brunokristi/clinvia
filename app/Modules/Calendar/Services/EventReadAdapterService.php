<?php

namespace App\Modules\Calendar\Services;

use App\Models\Branch;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;

class EventReadAdapterService
{
    public function __construct(
        private readonly EventFrontendMapper $mapper,
        private readonly RecurrenceExpansionService $recurrenceExpansionService,
    ) {
    }

    public function getLegacyCalendarPayload(Branch $branch, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $expandedOccurrences = $this->recurrenceExpansionService->forBranch($branch, $rangeStart, $rangeEnd);

        $events = $expandedOccurrences
            ->pluck('event')
            ->unique('id')
            ->values();

        $availabilityRules = $events
            ->where('type', EventType::AvailabilityRule)
            ->whereNull('recurrence_parent_id')
            ->values()
            ->map(fn (Event $event) => $this->mapper->mapForLegacyPayload($event))
            ->all();

        $calendarBookings = $expandedOccurrences
            ->filter(fn (array $occurrence) => $occurrence['event']->type === EventType::Booking)
            ->map(fn (array $occurrence) => $this->mapper->mapExpandedOccurrenceForLegacyPayload($occurrence))
            ->values()
            ->all();

        $calendarCapacityWindows = $expandedOccurrences
            ->filter(fn (array $occurrence) => $occurrence['event']->type === EventType::GroupEvent)
            ->map(fn (array $occurrence) => $this->mapper->mapExpandedOccurrenceForLegacyPayload($occurrence))
            ->values()
            ->all();

        return [
            'availabilityRules' => $availabilityRules,
            'calendarBookings' => $calendarBookings,
            'calendarCapacityWindows' => $calendarCapacityWindows,
        ];
    }
}
