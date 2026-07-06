<?php

namespace App\Modules\Calendar\Services;

use App\Models\Branch;
use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecurrenceExpansionService
{
    public function __construct(
        private readonly EventOccurrenceService $eventOccurrenceService,
    ) {
    }

    public function forBranch(Branch $branch, Carbon $rangeStart, Carbon $rangeEnd, ?array $types = null, bool $includeCancelled = false): Collection
    {
        $events = Event::query()
            ->with(['services', 'bookingDetail', 'availabilityRuleDetail', 'groupDetail', 'participants'])
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->when($types !== null && $types !== [], fn ($query) => $query->whereIn('type', $types))
            ->where(function ($query) use ($rangeStart, $rangeEnd) {
                $query
                    ->where(function ($subQuery) use ($rangeStart, $rangeEnd) {
                        $subQuery
                            ->where('starts_at', '<=', $rangeEnd)
                            ->where('ends_at', '>=', $rangeStart);
                    })
                    ->orWhere(function ($subQuery) use ($rangeEnd) {
                        $subQuery
                            ->where('is_recurring', true)
                            ->whereNull('recurrence_parent_id')
                            ->where('starts_at', '<=', $rangeEnd);
                    });
            })
            ->orderBy('starts_at')
            ->get();

        return $this->expandCollection($events, $rangeStart, $rangeEnd, $includeCancelled);
    }

    public function expandCollection(Collection $events, Carbon $rangeStart, Carbon $rangeEnd, bool $includeCancelled = false): Collection
    {
        $roots = $events
            ->filter(fn (Event $event) => $event->recurrence_parent_id === null)
            ->values();

        $overrides = $events
            ->filter(fn (Event $event) => $event->recurrence_parent_id !== null)
            ->groupBy('recurrence_parent_id');

        return $roots
            ->flatMap(fn (Event $root) => $this->expandRoot($root, $overrides->get($root->id, collect()), $rangeStart, $rangeEnd, $includeCancelled))
            ->values();
    }

    public function expandRoot(Event $root, Collection $overrides, Carbon $rangeStart, Carbon $rangeEnd, bool $includeCancelled = false): Collection
    {
        if (! $root->is_recurring || empty($root->recurrence_rule)) {
            if (! $this->overlapsRange($root, $rangeStart, $rangeEnd)) {
                return collect();
            }

            if (! $includeCancelled && $root->status === 'cancelled') {
                return collect();
            }

            return collect([
                $this->makeOccurrencePayload($root, $root->starts_at?->copy(), $root->ends_at?->copy(), false, false, $root->status === 'cancelled'),
            ]);
        }

        $occurrences = $this->eventOccurrenceService->getOccurrenceDates($root, $rangeStart, $rangeEnd);

        $overrideMap = $overrides
            ->filter(fn (Event $override) => $override->recurrence_original_starts_at)
            ->keyBy(fn (Event $override) => $override->recurrence_original_starts_at->copy()->utc()->format('Y-m-d\TH:i:s'));

        return $occurrences
            ->map(function (Carbon $occurrenceDate) use ($root, $overrideMap, $includeCancelled) {
                $occurrenceStartsAt = $this->combineOccurrenceDate($root->starts_at, $occurrenceDate);
                $occurrenceEndsAt = $this->combineOccurrenceDate($root->ends_at, $occurrenceDate);

                if (! $occurrenceStartsAt || ! $occurrenceEndsAt) {
                    return null;
                }

                $override = $overrideMap->get($occurrenceStartsAt->copy()->utc()->format('Y-m-d\TH:i:s'));

                if ($override) {
                    if (! $includeCancelled && $override->status === 'cancelled') {
                        return null;
                    }

                    return $this->makeOccurrencePayload(
                        $override,
                        $override->starts_at?->copy(),
                        $override->ends_at?->copy(),
                        true,
                        true,
                        $override->status === 'cancelled',
                        $root,
                        $occurrenceStartsAt,
                        $occurrenceEndsAt,
                    );
                }

                if (! $includeCancelled && $root->status === 'cancelled') {
                    return null;
                }

                return $this->makeOccurrencePayload(
                    $root,
                    $occurrenceStartsAt,
                    $occurrenceEndsAt,
                    true,
                    false,
                    $root->status === 'cancelled',
                    $root,
                    $occurrenceStartsAt,
                    $occurrenceEndsAt,
                );
            })
            ->filter()
            ->values();
    }

    private function makeOccurrencePayload(
        Event $event,
        ?Carbon $startsAt,
        ?Carbon $endsAt,
        bool $isOccurrence,
        bool $isOverride,
        bool $isCancelled,
        ?Event $rootEvent = null,
        ?Carbon $occurrenceOriginalStartsAt = null,
        ?Carbon $occurrenceOriginalEndsAt = null,
    ): array {
        $rootEvent ??= $event->recurrenceParent ?? $event;
        $occurrenceOriginalStartsAt ??= $event->recurrence_original_starts_at ?? $startsAt;
        $occurrenceOriginalEndsAt ??= $event->recurrence_original_ends_at ?? $endsAt;

        return [
            'event' => $event,
            'root_event' => $rootEvent,
            'event_id' => $event->id,
            'root_event_id' => $rootEvent->id,
            'occurrence_id' => sprintf('%d:%s', $rootEvent->id, $occurrenceOriginalStartsAt?->copy()->utc()->format('Y-m-d\TH:i:s') ?? 'unknown'),
            'occurrence_starts_at' => $startsAt,
            'occurrence_ends_at' => $endsAt,
            'occurrence_original_starts_at' => $occurrenceOriginalStartsAt,
            'occurrence_original_ends_at' => $occurrenceOriginalEndsAt,
            'is_recurring' => (bool) ($rootEvent->is_recurring || $isOccurrence),
            'is_occurrence' => $isOccurrence,
            'is_override' => $isOverride,
            'is_cancelled' => $isCancelled,
        ];
    }

    private function combineOccurrenceDate(?Carbon $source, Carbon $occurrenceDate): ?Carbon
    {
        if (! $source) {
            return null;
        }

        return Carbon::parse($occurrenceDate->toDateString() . ' ' . $source->format('H:i:s'), $source->getTimezone())
            ->setTimezone($source->getTimezone());
    }

    private function overlapsRange(Event $event, Carbon $rangeStart, Carbon $rangeEnd): bool
    {
        if (! $event->starts_at || ! $event->ends_at) {
            return false;
        }

        return $event->starts_at->lte($rangeEnd) && $event->ends_at->gte($rangeStart);
    }
}