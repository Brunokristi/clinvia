<?php

namespace App\Modules\Calendar\Services;

use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class RecurrenceOverrideService
{
    public function findOverride(Event $rootEvent, Carbon $occurrenceStartsAt): ?Event
    {
        return Event::query()
            ->where('recurrence_parent_id', $rootEvent->id)
            ->where('recurrence_original_starts_at', $occurrenceStartsAt)
            ->first();
    }

    public function upsertOverride(Event $rootEvent, Carbon $occurrenceStartsAt, Carbon $occurrenceEndsAt, array $payload, ?int $actorId = null): Event
    {
        $override = $this->findOverride($rootEvent, $occurrenceStartsAt) ?? new Event([
            'branch_id' => $rootEvent->branch_id,
            'type' => $rootEvent->type,
            'timezone' => $rootEvent->timezone,
            'recurrence_parent_id' => $rootEvent->id,
            'recurrence_original_starts_at' => $occurrenceStartsAt,
            'recurrence_original_ends_at' => $occurrenceEndsAt,
            'split_from_event_id' => null,
            'recurrence_sequence' => null,
            'metadata' => [],
            'created_by' => $actorId,
        ]);

        if (! $override->exists) {
            $override->branch_id = $rootEvent->branch_id;
            $override->type = $rootEvent->type;
            $override->timezone = $rootEvent->timezone;
            $override->recurrence_parent_id = $rootEvent->id;
            $override->recurrence_original_starts_at = $occurrenceStartsAt;
            $override->recurrence_original_ends_at = $occurrenceEndsAt;
            $override->metadata = $rootEvent->metadata ?? [];
            $override->created_by = $actorId;
        }

        $override->starts_at = isset($payload['starts_at']) ? Carbon::parse($payload['starts_at']) : ($override->starts_at ?? $occurrenceStartsAt);
        $override->ends_at = isset($payload['ends_at']) ? Carbon::parse($payload['ends_at']) : ($override->ends_at ?? $occurrenceEndsAt);
        $override->status = $payload['status'] ?? ($override->status ?: $rootEvent->status);
        $override->title = $payload['title'] ?? ($override->title ?: $rootEvent->title);
        $override->description = Arr::exists($payload, 'description') ? $payload['description'] : ($override->description ?: $rootEvent->description);
        $override->recurrence_rule = null;
        $override->is_recurring = false;
        $override->updated_by = $actorId;
        $override->save();

        return $override->fresh();
    }
}