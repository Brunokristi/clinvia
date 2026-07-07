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
        $expandedOccurrencesWithCancelled = $this->recurrenceExpansionService->forBranch(
            $branch,
            $rangeStart,
            $rangeEnd,
            null,
            true,
        );

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

        $availabilityOverrideMap = $expandedOccurrencesWithCancelled
            ->filter(fn (array $occurrence) => $occurrence['event']->type === EventType::AvailabilityRule)
            ->filter(fn (array $occurrence) => (bool) ($occurrence['is_override'] ?? false))
            ->groupBy(fn (array $occurrence) => (int) $occurrence['root_event_id'])
            ->map(function ($occurrences) {
                return $occurrences
                    ->map(function (array $occurrence): array {
                        /** @var Event $override */
                        $override = $occurrence['event'];

                        return [
                            'root_event_id' => (int) ($occurrence['root_event_id'] ?? 0),
                            'original_date' => $override->recurrence_original_starts_at?->toDateString(),
                            'date' => ($occurrence['occurrence_starts_at'] ?? null)?->toDateString(),
                            'starts_at' => ($occurrence['occurrence_starts_at'] ?? null)?->format('H:i'),
                            'ends_at' => ($occurrence['occurrence_ends_at'] ?? null)?->format('H:i'),
                            'status' => $override->status,
                        ];
                    })
                    ->filter(fn (array $override) => ! empty($override['original_date']) && ! empty($override['date']))
                    ->values()
                    ->all();
            })
            ->all();

        $availabilityRules = collect($availabilityRules)
            ->map(function (array $rule) use ($availabilityOverrideMap): array {
                $eventId = (int) ($rule['id'] ?? 0);

                return [
                    ...$rule,
                    'occurrence_overrides' => $availabilityOverrideMap[$eventId] ?? [],
                ];
            })
            ->values()
            ->all();

        $calendarBookings = $expandedOccurrences
            ->filter(fn (array $occurrence) => $occurrence['event']->type === EventType::Booking)
            ->map(fn (array $occurrence) => $this->mapper->mapExpandedOccurrenceForLegacyPayload($occurrence))
            ->values()
            ->all();

        $calendarCapacityWindows = $expandedOccurrencesWithCancelled
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
