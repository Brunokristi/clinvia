<?php

namespace App\Modules\Calendar\Services;

use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class RecurringEventSplitService
{
    public function __construct(
        private readonly EventOccurrenceService $eventOccurrenceService,
    ) {
    }

    public function split(Event $rootEvent, Carbon $occurrenceStartsAt, Carbon $occurrenceEndsAt, array $payload, ?int $actorId = null): array
    {
        $originalRule = $rootEvent->recurrence_rule ?? [];
        $untilDate = $this->previousOccurrenceDate($rootEvent, $occurrenceStartsAt);

        $oldRule = $originalRule;
        data_set($oldRule, 'ends.type', 'on');
        data_set($oldRule, 'ends.until', $untilDate?->toDateString());

        $rootEvent->recurrence_rule = $oldRule;
        $rootEvent->updated_by = $actorId;
        $rootEvent->save();

        $newRoot = $rootEvent->replicate([
            'created_at',
            'updated_at',
            'deleted_at',
            'cancelled_at',
        ]);

        $newRoot->recurrence_parent_id = null;
        $newRoot->split_from_event_id = $rootEvent->id;
        $newRoot->starts_at = isset($payload['starts_at']) ? Carbon::parse($payload['starts_at']) : $occurrenceStartsAt;
        $newRoot->ends_at = isset($payload['ends_at']) ? Carbon::parse($payload['ends_at']) : $occurrenceEndsAt;
        $newRoot->recurrence_rule = Arr::exists($payload, 'recurrence_rule') ? $payload['recurrence_rule'] : $originalRule;
        $newRoot->is_recurring = ! empty($newRoot->recurrence_rule);
        $newRoot->recurrence_sequence = ((int) ($rootEvent->recurrence_sequence ?? 0)) + 1;
        $newRoot->created_by = $actorId;
        $newRoot->updated_by = $actorId;

        $metadata = $newRoot->metadata ?? [];
        $metadata['series_split_from'] = $occurrenceStartsAt->toDateString();
        $newRoot->metadata = $metadata;
        $newRoot->save();

        return [
            'old_root' => $rootEvent->fresh(),
            'new_root' => $newRoot->fresh(),
            'future_override_ids' => $this->reassignFutureOverrides($rootEvent, $newRoot, $occurrenceStartsAt),
        ];
    }

    private function previousOccurrenceDate(Event $rootEvent, Carbon $occurrenceStartsAt): ?Carbon
    {
        if (! $rootEvent->starts_at) {
            return null;
        }

        $seriesStart = $rootEvent->starts_at->copy()->startOfDay();
        $rangeEnd = $occurrenceStartsAt->copy()->subDay()->endOfDay();

        if ($rangeEnd->lt($seriesStart)) {
            return null;
        }

        return $this->eventOccurrenceService
            ->getOccurrenceDates($rootEvent, $seriesStart, $rangeEnd)
            ->last();
    }

    private function reassignFutureOverrides(Event $oldRoot, Event $newRoot, Carbon $occurrenceStartsAt): array
    {
        $futureOverrides = Event::query()
            ->where('recurrence_parent_id', $oldRoot->id)
            ->where('recurrence_original_starts_at', '>=', $occurrenceStartsAt)
            ->get();

        $affectedIds = [];

        /** @var Event $override */
        foreach ($futureOverrides as $override) {
            $override->recurrence_parent_id = $newRoot->id;
            $override->save();
            $affectedIds[] = $override->id;
        }

        return $affectedIds;
    }
}