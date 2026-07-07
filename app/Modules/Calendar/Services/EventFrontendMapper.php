<?php

namespace App\Modules\Calendar\Services;

use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;

class EventFrontendMapper
{
    public function mapExpandedOccurrenceForCalendar(array $occurrence): array
    {
        /** @var Event $event */
        $event = $occurrence['event'];
        /** @var Event $rootEvent */
        $rootEvent = $occurrence['root_event'];
        /** @var Carbon|null $startsAt */
        $startsAt = $occurrence['occurrence_starts_at'] ?? null;
        /** @var Carbon|null $endsAt */
        $endsAt = $occurrence['occurrence_ends_at'] ?? null;

        return [
            'id' => $occurrence['occurrence_id'],
            'title' => $event->display_title,
            'start' => $this->formatDateTime($startsAt),
            'end' => $this->formatDateTime($endsAt),
            'type' => $event->type?->value,
            'status' => $event->status,
            'editable' => $event->status !== 'cancelled' && $event->deleted_at === null,
            'extendedProps' => [
                'event_id' => $event->id,
                'root_event_id' => $rootEvent->id,
                'occurrence_id' => $occurrence['occurrence_id'],
                'occurrence_starts_at' => $startsAt?->toIso8601String(),
                'occurrence_ends_at' => $endsAt?->toIso8601String(),
                'event_type' => $event->type?->value,
                'event_status' => $event->status,
                'calendar_event_id' => sprintf('event-%s', $occurrence['occurrence_id']),
                'is_recurring' => (bool) ($occurrence['is_recurring'] ?? false),
                'is_occurrence' => (bool) ($occurrence['is_occurrence'] ?? false),
                'is_override' => (bool) ($occurrence['is_override'] ?? false),
                'recurrence_rule' => $rootEvent->recurrence_rule,
                'recurrence_parent_id' => $event->recurrence_parent_id,
                'metadata' => $event->metadata,
                'booking' => $this->mapBookingOccurrenceDetail($occurrence),
                'availability_rule' => $this->mapAvailabilityRuleDetail($event),
                'group_event' => $this->mapGroupOccurrenceDetail($occurrence),
            ],
        ];
    }

    public function mapForCalendar(Event $event): array
    {
        $title = $event->display_title;

        return [
            'id' => $event->id,
            'title' => $title,
            'start' => $this->formatDateTime($event->starts_at),
            'end' => $this->formatDateTime($event->ends_at),
            'type' => $event->type?->value,
            'status' => $event->status,
            'editable' => $event->status !== 'cancelled' && $event->deleted_at === null,
            'extendedProps' => [
                'event_id' => $event->id,
                'event_type' => $event->type?->value,
                'event_status' => $event->status,
                'calendar_event_id' => sprintf('event-%s', $event->id),
                'is_recurring' => (bool) $event->is_recurring,
                'recurrence_rule' => $event->recurrence_rule,
                'recurrence_parent_id' => $event->recurrence_parent_id,
                'metadata' => $event->metadata,
                'booking' => $this->mapBookingDetail($event),
                'availability_rule' => $this->mapAvailabilityRuleDetail($event),
                'group_event' => $this->mapGroupEventDetail($event),
            ],
        ];
    }

    public function mapForLegacyPayload(Event $event): array
    {
        $legacyRepeat = $this->mapLegacyRepeatFields($event);

        $base = [
            'id' => $event->id,
            'date' => $event->starts_at?->toDateString(),
            'starts_at' => $this->formatCalendarDateTime($event->starts_at),
            'ends_at' => $this->formatCalendarDateTime($event->ends_at),
            'starts_datetime' => $this->formatCalendarDateTime($event->starts_at),
            'ends_datetime' => $this->formatCalendarDateTime($event->ends_at),
            'status' => $event->status,
            'series_uuid' => data_get($event->metadata, 'series_uuid'),
            'recurrence' => $event->recurrence_rule,
            'recurrence_excluded_dates' => data_get($event->metadata, 'recurrence_excluded_dates', []),
            ...$legacyRepeat,
        ];

        return match ($event->type) {
            EventType::Booking => [
                ...$base,
                'calendar_event_id' => sprintf('booking-%s', $event->id),
                'booking_slot_id' => null,
                'capacity_window_id' => null,
                'service_id' => $event->services->first()?->id,
                'service_ids' => $event->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'service_name' => $event->services->pluck('name')->join(', '),
                'services' => $event->services->map(fn ($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                ])->values()->all(),
                'patient_name' => $event->bookingDetail?->patient_name,
                'patient_email' => $event->bookingDetail?->patient_email,
                'patient_phone' => $event->bookingDetail?->patient_phone,
                'patient_birth_number' => $event->bookingDetail?->patient_birth_number,
                'patient_note' => $event->bookingDetail?->public_notes,
                'admin_note' => $event->bookingDetail?->internal_notes,
            ],
            EventType::AvailabilityRule => [
                ...$base,
                'rule_id' => $event->id,
                'day_of_week' => $event->starts_at?->dayOfWeekIso,
                'starts_at' => $event->starts_at?->format('H:i'),
                'ends_at' => $event->ends_at?->format('H:i'),
                'service_ids' => $event->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'excluded_dates' => data_get($event->metadata, 'recurrence_excluded_dates', []),
                'is_enabled' => $event->status !== 'cancelled',
            ],
            EventType::GroupEvent => [
                ...$base,
                'capacity_window_id' => $event->id,
                'service_id' => $event->groupDetail?->service_id,
                'service_name' => $event->groupDetail?->service_name,
                'capacity' => (int) ($event->groupDetail?->capacity ?? 0),
                'booked_count' => (int) ($event->groupDetail?->reserved_places ?? 0),
                'available_count' => max(
                    0,
                    (int) ($event->groupDetail?->capacity ?? 0) - (int) ($event->groupDetail?->reserved_places ?? 0),
                ),
                'admin_note' => $event->groupDetail?->notes,
                'bookings' => $event->participants
                    ->where('status', 'confirmed')
                    ->values()
                    ->map(fn ($participant) => [
                        'id' => $participant->id,
                        'patient_name' => $participant->participant_name,
                        'patient_email' => $participant->participant_email,
                        'patient_phone' => $participant->participant_phone,
                        'patient_birth_number' => $participant->participant_birth_number,
                        'status' => $participant->status,
                        'starts_at' => $this->formatDateTime($event->starts_at),
                        'ends_at' => $this->formatDateTime($event->ends_at),
                    ])
                    ->all(),
            ],
            default => $base,
        };
    }

    private function mapLegacyRepeatFields(Event $event): array
    {
        $recurrence = $event->recurrence_rule ?? null;
        $frequency = data_get($recurrence, 'frequency');
        $repeatUnit = match ($frequency) {
            'daily' => 'days',
            'monthly' => 'months',
            'yearly' => 'months',
            default => 'weeks',
        };
        $interval = max(1, (int) data_get($recurrence, 'interval', 1));

        if ($frequency === 'yearly') {
            $interval *= 12;
        }

        return [
            'repeats' => ! empty($recurrence),
            'repeat_every' => ! empty($recurrence) ? $interval : 1,
            'repeat_unit' => ! empty($recurrence) ? $repeatUnit : 'weeks',
            'repeat_weekdays' => $repeatUnit === 'weeks'
                ? collect(data_get($recurrence, 'weekdays', []))->values()->all()
                : [],
            'repeat_ends_on' => data_get($recurrence, 'ends.type') === 'on'
                ? data_get($recurrence, 'ends.until')
                : null,
        ];
    }

    public function mapForLegacyOccurrence(Event $event, Carbon $occurrenceDate): array
    {
        $payload = $this->mapForLegacyPayload($event);
        $occurrenceStartsAt = $this->withOccurrenceDate($event->starts_at, $occurrenceDate);
        $occurrenceEndsAt = $this->withOccurrenceDate($event->ends_at, $occurrenceDate);

        $payload['date'] = $occurrenceDate->toDateString();
        $payload['starts_at'] = $this->formatCalendarDateTime($occurrenceStartsAt);
        $payload['ends_at'] = $this->formatCalendarDateTime($occurrenceEndsAt);
        $payload['starts_datetime'] = $this->formatCalendarDateTime($occurrenceStartsAt);
        $payload['ends_datetime'] = $this->formatCalendarDateTime($occurrenceEndsAt);
        $payload['occurrence_date'] = $occurrenceDate->toDateString();
        $payload['is_recurring'] = true;

        if ($event->type === EventType::Booking) {
            $payload['calendar_event_id'] = sprintf('booking-%s-%s', $event->id, $occurrenceDate->toDateString());
        }

        if ($event->type === EventType::GroupEvent) {
            $payload['calendar_event_id'] = sprintf('capacity-window-%s-%s', $event->id, $occurrenceDate->toDateString());
            $payload['bookings'] = collect($payload['bookings'] ?? [])
                ->map(function (array $booking) use ($occurrenceStartsAt, $occurrenceEndsAt): array {
                    return [
                        ...$booking,
                        'starts_at' => $this->formatDateTime($occurrenceStartsAt),
                        'ends_at' => $this->formatDateTime($occurrenceEndsAt),
                    ];
                })
                ->values()
                ->all();
        }

        return $payload;
    }

    public function mapExpandedOccurrenceForLegacyPayload(array $occurrence): array
    {
        /** @var Event $event */
        $event = $occurrence['event'];
        /** @var Event $rootEvent */
        $rootEvent = $occurrence['root_event'];
        /** @var Carbon|null $startsAt */
        $startsAt = $occurrence['occurrence_starts_at'] ?? null;
        /** @var Carbon|null $endsAt */
        $endsAt = $occurrence['occurrence_ends_at'] ?? null;

        $payload = $this->mapForLegacyPayload($event);

        if ((bool) ($occurrence['is_recurring'] ?? false)) {
            $payload = [
                ...$payload,
                'series_uuid' => data_get($rootEvent->metadata, 'series_uuid'),
                'recurrence' => $rootEvent->recurrence_rule,
                'recurrence_excluded_dates' => data_get($rootEvent->metadata, 'recurrence_excluded_dates', []),
                ...$this->mapLegacyRepeatFields($rootEvent),
            ];
        }

        $payload['id'] = $event->id;
        $payload['root_event_id'] = $occurrence['root_event_id'] ?? $rootEvent->root_event_id ?? $rootEvent->id;
        $payload['logical_root_event_id'] = $payload['root_event_id'];
        $payload['recurring_master_id'] = $occurrence['recurring_master_id'] ?? $rootEvent->id;
        $payload['occurrence_id'] = $occurrence['occurrence_id'];
        $payload['occurrence_starts_at'] = $startsAt?->toIso8601String();
        $payload['occurrence_ends_at'] = $endsAt?->toIso8601String();
        $payload['display_key'] = $occurrence['display_key'] ?? null;
        $payload['date'] = $startsAt?->toDateString();
        $payload['starts_at'] = $this->formatCalendarDateTime($startsAt);
        $payload['ends_at'] = $this->formatCalendarDateTime($endsAt);
        $payload['starts_datetime'] = $this->formatCalendarDateTime($startsAt);
        $payload['ends_datetime'] = $this->formatCalendarDateTime($endsAt);
        $payload['is_recurring'] = (bool) ($occurrence['is_recurring'] ?? false);
        $payload['is_occurrence'] = (bool) ($occurrence['is_occurrence'] ?? false);
        $payload['is_override'] = (bool) ($occurrence['is_override'] ?? false);

        if ($event->type === EventType::Booking) {
            $payload['calendar_event_id'] = sprintf('booking-%s', $occurrence['occurrence_id']);
            $payload['occurrence_date'] = $startsAt?->toDateString();
            $payload['occurrence_original_date'] = ($occurrence['occurrence_original_starts_at'] ?? null)?->toDateString();
        }

        if ($event->type === EventType::GroupEvent) {
            $payload['calendar_event_id'] = sprintf('capacity-window-%s', $occurrence['occurrence_id']);
            $payload['occurrence_date'] = $startsAt?->toDateString();
            $payload['occurrence_original_date'] = ($occurrence['occurrence_original_starts_at'] ?? null)?->toDateString();
            $payload['bookings'] = collect($payload['bookings'] ?? [])
                ->map(fn (array $booking) => [
                    ...$booking,
                    'starts_at' => $this->formatDateTime($startsAt),
                    'ends_at' => $this->formatDateTime($endsAt),
                ])
                ->values()
                ->all();
        }

        return $payload;
    }

    private function mapBookingDetail(Event $event): ?array
    {
        if (! $event->bookingDetail) {
            return null;
        }

        return [
            'patient_id' => $event->bookingDetail->patient_id,
            'patient_name' => $event->bookingDetail->patient_name,
            'patient_email' => $event->bookingDetail->patient_email,
            'patient_phone' => $event->bookingDetail->patient_phone,
            'patient_birth_number' => $event->bookingDetail->patient_birth_number,
            'booking_status' => $event->bookingDetail->booking_status,
            'booking_source' => $event->bookingDetail->booking_source,
            'internal_notes' => $event->bookingDetail->internal_notes,
            'public_notes' => $event->bookingDetail->public_notes,
        ];
    }

    private function mapBookingOccurrenceDetail(array $occurrence): ?array
    {
        /** @var Event $event */
        $event = $occurrence['event'];
        $detail = $this->mapBookingDetail($event);

        if (! $detail) {
            return null;
        }

        return [
            ...$detail,
            'root_event_id' => $occurrence['root_event_id'] ?? $event->id,
            'occurrence_id' => $occurrence['occurrence_id'] ?? null,
            'occurrence_starts_at' => ($occurrence['occurrence_starts_at'] ?? null)?->toIso8601String(),
            'occurrence_ends_at' => ($occurrence['occurrence_ends_at'] ?? null)?->toIso8601String(),
        ];
    }

    private function mapAvailabilityRuleDetail(Event $event): ?array
    {
        if (! $event->availabilityRuleDetail) {
            return null;
        }

        return [
            'capacity_rules' => $event->availabilityRuleDetail->capacity_rules,
            'visibility_rules' => $event->availabilityRuleDetail->visibility_rules,
            'slot_interval_minutes' => $event->availabilityRuleDetail->slot_interval_minutes,
            'buffer_before_minutes' => $event->availabilityRuleDetail->buffer_before_minutes,
            'buffer_after_minutes' => $event->availabilityRuleDetail->buffer_after_minutes,
            'online_booking_rules' => $event->availabilityRuleDetail->online_booking_rules,
        ];
    }

    private function mapGroupEventDetail(Event $event): ?array
    {
        if (! $event->groupDetail) {
            return null;
        }

        return [
            'service_id' => $event->groupDetail->service_id,
            'service_name' => $event->groupDetail->service_name,
            'capacity' => (int) $event->groupDetail->capacity,
            'reserved_places' => (int) $event->groupDetail->reserved_places,
            'available_places' => $event->groupDetail->available_places,
            'group_status' => $event->groupDetail->group_status,
        ];
    }

    private function mapGroupOccurrenceDetail(array $occurrence): ?array
    {
        /** @var Event $event */
        $event = $occurrence['event'];
        $detail = $this->mapGroupEventDetail($event);

        if (! $detail) {
            return null;
        }

        return [
            ...$detail,
            'root_event_id' => $occurrence['root_event_id'] ?? $event->id,
            'occurrence_id' => $occurrence['occurrence_id'] ?? null,
            'occurrence_starts_at' => ($occurrence['occurrence_starts_at'] ?? null)?->toIso8601String(),
            'occurrence_ends_at' => ($occurrence['occurrence_ends_at'] ?? null)?->toIso8601String(),
        ];
    }

    private function formatDateTime(?Carbon $value): ?string
    {
        return $value?->toIso8601String();
    }

    private function formatCalendarDateTime(?Carbon $value): ?string
    {
        return $value?->format('Y-m-d\TH:i:s');
    }

    private function withOccurrenceDate(?Carbon $source, Carbon $occurrenceDate): ?Carbon
    {
        if (! $source) {
            return null;
        }

        return Carbon::parse($occurrenceDate->toDateString() . ' ' . $source->format('H:i:s'));
    }
}
