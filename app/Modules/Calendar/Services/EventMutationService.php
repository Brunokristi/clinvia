<?php

namespace App\Modules\Calendar\Services;

use App\Models\AppointmentRequest;
use App\Models\Branch;
use App\Models\Service;
use App\Modules\Calendar\Enums\EventAction;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\AvailabilityRuleEventDetail;
use App\Modules\Calendar\Models\BookingEventDetail;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Models\GroupEventDetail;
use App\Modules\Calendar\Models\GroupEventParticipant;
use App\Services\DisabledDayService;
use App\Services\OpeningHoursService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class EventMutationService
{
    public function __construct(
        private readonly EventNotificationService $eventNotificationService,
        private readonly EventOccurrenceService $eventOccurrenceService,
        private readonly RecurrenceOverrideService $recurrenceOverrideService,
        private readonly RecurringEventSplitService $recurringEventSplitService,
        private readonly DisabledDayService $disabledDayService,
        private readonly OpeningHoursService $openingHoursService,
    ) {
    }

    public function create(Branch $branch, array $payload, ?int $actorId = null): Event
    {
        $type = EventType::from((string) $payload['type']);

        $this->validatePayload($type, $payload);

        $startsAt = isset($payload['starts_at']) ? Carbon::parse($payload['starts_at']) : null;
        $endsAt = isset($payload['ends_at']) ? Carbon::parse($payload['ends_at']) : null;

        if ($startsAt && $endsAt && $this->shouldEnforceServiceDuration($type)) {
            $this->validateWindowMeetsServiceDuration(
                $startsAt,
                $endsAt,
                $this->resolveRequiredServiceDurationMinutes(null, $payload),
            );
        }

        if (isset($payload['starts_at']) && isset($payload['ends_at'])) {
            $this->validateEventWindowIsAllowed(
                $branch,
                $startsAt,
                $endsAt,
            );
        }

        $event = DB::transaction(function () use ($branch, $payload, $type, $actorId): Event {
            $event = Event::query()->create([
                'branch_id' => $branch->id,
                'type' => $type,
                'status' => $payload['status'] ?? 'confirmed',
                'starts_at' => isset($payload['starts_at']) ? Carbon::parse($payload['starts_at']) : null,
                'ends_at' => isset($payload['ends_at']) ? Carbon::parse($payload['ends_at']) : null,
                'timezone' => $payload['timezone'] ?? config('app.timezone'),
                'title' => $payload['title'] ?? null,
                'description' => $payload['description'] ?? null,
                'recurrence_rule' => $payload['recurrence_rule'] ?? null,
                'is_recurring' => ! empty($payload['recurrence_rule']),
                'metadata' => $this->prepareMetadata($payload),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $event->root_event_id = $event->id;
            $event->save();

            $this->persistTypeDetails($event, $payload);
            $this->syncServices($event, $payload['services'] ?? []);

            return $event->fresh($this->relations());
        });

        $this->eventNotificationService->dispatchMutationSignals($event, EventAction::EventCreated);

        return $event;
    }

    private function prepareMetadata(array $payload): array
    {
        $metadata = $payload['metadata'] ?? [];

        if (! empty($payload['recurrence_rule']) && empty($metadata['series_uuid'])) {
            $metadata['series_uuid'] = (string) Str::uuid();
        }

        return $metadata;
    }

    public function update(Event $event, array $payload, ?int $actorId = null, ?string $scope = null): Event
    {
        $scope = $this->eventOccurrenceService->resolveScope($scope)->value;
        $event = $this->resolveScopedMutationEvent($event, $scope, $payload);
        $event->loadMissing($this->relations());
        $payload = $this->normalizePayloadForUpdate($event, $payload);
        $beforeSnapshot = $this->notificationSnapshot($event);
        $this->validateRecurringMutation($event, $payload, $scope);

        $affectedEventIds = [];

        $updatedEvent = DB::transaction(function () use ($event, $payload, $actorId, $scope, &$affectedEventIds): Event {
            if ($scope === 'series' && $event->is_recurring && $event->recurrence_parent_id === null) {
                $result = $this->updateLogicalSeries($event, $payload, $actorId);
                $affectedEventIds = $result['affected_event_ids'];

                return $result['event'];
            }

            if ($scope !== 'series' && $event->is_recurring && $this->resolveOccurrenceStartsAt($event, $payload)) {
                $event = $this->updateRecurringOccurrence($event, $payload, $actorId, $scope);
                $affectedEventIds = [$event->id];

                return $event->fresh($this->relations());
            }

            $affectedEventIds = [$event->id];

            return $this->applyPayloadToEvent($event, $payload, $actorId)->fresh($this->relations());
        });

        $action = match ($scope) {
            'this' => EventAction::EventOccurrenceUpdated,
            'this_and_following' => EventAction::EventSeriesSplit,
            'series' => EventAction::EventSeriesUpdated,
            default => EventAction::EventUpdated,
        };

        $this->eventNotificationService->dispatchMutationSignals(
            event: $updatedEvent,
            action: $action,
            affectedEventIds: $affectedEventIds !== [] ? $affectedEventIds : [$updatedEvent->id],
            recurrenceScope: $scope,
            occurrenceStartsAt: $this->resolveOccurrenceStartsAt($event, $payload)?->toIso8601String(),
            context: [
                'old_snapshot' => $beforeSnapshot,
                'new_snapshot' => $this->notificationSnapshot($updatedEvent),
                'occurrence_ends_at' => $this->resolveOccurrenceEndsAt(
                    $event,
                    $payload,
                    $this->resolveOccurrenceStartsAt($event, $payload),
                )?->toIso8601String(),
                'occurrence_original_starts_at' => $event->recurrence_original_starts_at?->toIso8601String(),
                'occurrence_display_key' => $this->resolveOccurrenceDisplayKey($event, $payload),
            ],
        );

        return $updatedEvent;
    }

    public function reschedule(Event $event, Carbon $startsAt, Carbon $endsAt, ?int $actorId = null, ?string $scope = null, ?Carbon $occurrenceDate = null): Event
    {
        $payload = [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'occurrence_date' => $occurrenceDate?->toDateString(),
        ];

        $updated = $this->update($event, $payload, $actorId, $scope);

        $this->eventNotificationService->dispatchMutationSignals(
            event: $updated,
            action: EventAction::EventRescheduled,
            affectedEventIds: [$updated->id],
            recurrenceScope: $scope,
        );

        return $updated;
    }

    public function resize(Event $event, Carbon $endsAt, ?int $actorId = null, ?string $scope = null): Event
    {
        $updated = $this->update($event, [
            'ends_at' => $endsAt,
        ], $actorId, $scope);

        $this->eventNotificationService->dispatchMutationSignals(
            event: $updated,
            action: EventAction::EventResized,
            affectedEventIds: [$updated->id],
            recurrenceScope: $scope,
        );

        return $updated;
    }

    public function cancel(Event $event, ?int $actorId = null, ?string $scope = null): Event
    {
        $scope = $this->eventOccurrenceService->resolveScope($scope)->value;
        $event = $this->resolveScopedMutationEvent($event, $scope, [
            'occurrence_starts_at' => request('occurrence_starts_at'),
            'occurrence_date' => request('occurrence_date'),
        ]);

        $updated = $this->update($event, [
            'status' => 'cancelled',
            'occurrence_starts_at' => request('occurrence_starts_at'),
            'occurrence_ends_at' => request('occurrence_ends_at'),
            'occurrence_date' => request('occurrence_date'),
            'metadata' => array_merge($event->metadata ?? [], [
                'cancelled_at' => now()->toIso8601String(),
            ]),
        ], $actorId, $scope);

        $updated->cancelled_at = now();
        $updated->save();

        if ($scope === 'series') {
            $this->cascadeSeriesCancellation($updated, $actorId);
        }

        $this->eventNotificationService->dispatchMutationSignals(
            event: $updated,
            action: $scope === 'this' ? EventAction::EventOccurrenceCancelled : EventAction::EventCancelled,
            affectedEventIds: [$updated->id],
            recurrenceScope: $scope,
        );

        return $updated;
    }

    public function delete(Event $event, ?string $scope = null): void
    {
        $scope = $this->eventOccurrenceService->resolveScope($scope)->value;
        $event = $this->resolveScopedMutationEvent($event, $scope, [
            'occurrence_starts_at' => request('occurrence_starts_at'),
            'occurrence_date' => request('occurrence_date'),
        ]);
        $event->loadMissing($this->relations());
        $beforeSnapshot = $this->notificationSnapshot($event);
        $eventSnapshot = $this->notificationEventSnapshot($event);

        DB::transaction(function () use ($event, $scope): void {
            $occurrenceStartsAt = $this->resolveOccurrenceStartsAt($event, [
                'occurrence_starts_at' => request('occurrence_starts_at'),
                'occurrence_date' => request('occurrence_date'),
            ]);

            if ($scope !== 'series' && $event->is_recurring && $occurrenceStartsAt) {
                $occurrenceEndsAt = $this->resolveOccurrenceEndsAt($event, [
                    'occurrence_ends_at' => request('occurrence_ends_at'),
                    'occurrence_starts_at' => request('occurrence_starts_at'),
                    'occurrence_date' => request('occurrence_date'),
                ], $occurrenceStartsAt);

                if ($scope === 'this') {
                    $this->recurrenceOverrideService->upsertOverride($event, $occurrenceStartsAt, $occurrenceEndsAt, [
                        'status' => 'cancelled',
                        'starts_at' => $occurrenceStartsAt,
                        'ends_at' => $occurrenceEndsAt,
                    ]);

                    return;
                }

                if ($scope === 'this_and_following') {
                    $this->deleteThisAndFollowingAcrossLogicalSeries($event, $occurrenceStartsAt);

                    return;
                }

                return;
            }

            if ($scope === 'series') {
                $this->deleteLogicalSeries($event);

                return;
            }

            $event->delete();
        });

        $this->eventNotificationService->dispatchMutationSignals(
            event: $event,
            action: match ($scope) {
                'this' => EventAction::EventOccurrenceDeleted,
                'series' => EventAction::EventSeriesDeleted,
                default => EventAction::EventDeleted,
            },
            affectedEventIds: [$event->id],
            recurrenceScope: $scope,
            context: [
                'old_snapshot' => $beforeSnapshot,
                'new_snapshot' => $beforeSnapshot,
                'occurrence_display_key' => $this->resolveOccurrenceDisplayKey($event, [
                    'occurrence_starts_at' => request('occurrence_starts_at'),
                    'occurrence_date' => request('occurrence_date'),
                ]),
                'event_snapshot' => $eventSnapshot,
            ],
        );
    }

    public function duplicate(Event $event, ?int $actorId = null): Event
    {
        $duplicate = DB::transaction(function () use ($event, $actorId): Event {
            $isRecurringMaster = $event->is_recurring
                && $event->recurrence_parent_id === null
                && ! empty($event->recurrence_rule);

            $copy = $event->replicate([
                'created_at',
                'updated_at',
                'deleted_at',
                'cancelled_at',
            ]);

            $copy->status = 'confirmed';
            $copy->is_recurring = $isRecurringMaster;
            $copy->recurrence_rule = $isRecurringMaster ? $event->recurrence_rule : null;
            $copy->recurrence_parent_id = null;
            $copy->recurrence_exception_date = null;
            $copy->recurrence_original_starts_at = null;
            $copy->recurrence_original_ends_at = null;
            $copy->split_from_event_id = null;
            $copy->root_event_id = null;
            $copy->recurrence_sequence = null;
            $copy->created_by = $actorId;
            $copy->updated_by = $actorId;

            $copyMetadata = $copy->metadata ?? [];
            unset($copyMetadata['recurrence_excluded_dates']);

            if ($isRecurringMaster) {
                $copyMetadata['series_uuid'] = (string) Str::uuid();
            }

            $copy->metadata = $copyMetadata;

            $copy->save();
            $copy->root_event_id = $copy->id;
            $copy->save();

            $this->duplicateDetails($event, $copy);
            $this->duplicateServices($event, $copy);

            return $copy->fresh($this->relations());
        });

        $this->eventNotificationService->dispatchMutationSignals($duplicate, EventAction::EventDuplicated);

        return $duplicate;
    }

    public function updateServices(Event $event, array $services, ?int $actorId = null): Event
    {
        $updated = DB::transaction(function () use ($event, $services, $actorId): Event {
            $startsAt = $event->starts_at;
            $endsAt = $event->ends_at;

            if ($startsAt && $endsAt && $this->shouldEnforceServiceDuration($event->type)) {
                $this->validateWindowMeetsServiceDuration(
                    $startsAt,
                    $endsAt,
                    $this->resolveDurationFromServicePayload($services),
                );
            }

            $this->syncServices($event, $services);
            $event->updated_by = $actorId;
            $event->save();

            return $event->fresh($this->relations());
        });

        $this->eventNotificationService->dispatchMutationSignals($updated, EventAction::EventServicesUpdated);

        return $updated;
    }

    public function addGroupParticipant(Event $event, array $payload): GroupEventParticipant
    {
        if ($event->type !== EventType::GroupEvent) {
            throw ValidationException::withMessages([
                'event_id' => 'Ucastnika je mozne pridat iba do group_event.',
            ]);
        }

        if ($event->is_recurring && $event->recurrence_parent_id === null && ! filled($payload['occurrence_starts_at'] ?? null) && ! filled($payload['occurrence_date'] ?? null)) {
            throw ValidationException::withMessages([
                'occurrence_starts_at' => 'Pri opakovanom skupinovom termine je potrebne vybrat konkretnu occurrence.',
            ]);
        }

        if ($event->is_recurring && $event->recurrence_parent_id === null && filled($payload['occurrence_starts_at'] ?? null)) {
            $event = $this->materializeOccurrence(
                event: $event,
                occurrenceStartsAt: Carbon::parse($payload['occurrence_starts_at']),
                occurrenceEndsAt: filled($payload['occurrence_ends_at'] ?? null)
                    ? Carbon::parse($payload['occurrence_ends_at'])
                    : null,
            );
        }

        $participant = DB::transaction(function () use ($event, $payload): GroupEventParticipant {
            $event->loadMissing('groupDetail');

            if (! $event->groupDetail) {
                throw ValidationException::withMessages([
                    'event_id' => 'Group event nema detail.',
                ]);
            }

            if ($event->groupDetail->available_places <= 0) {
                throw ValidationException::withMessages([
                    'capacity' => 'Skupinovy termin je uz naplneny.',
                ]);
            }

            $participant = $event->participants()->create([
                'source_request_id' => $payload['source_request_id'] ?? null,
                'patient_id' => $payload['patient_id'] ?? null,
                'status' => $payload['status'] ?? 'confirmed',
                'booked_at' => now(),
                'notes' => $payload['notes'] ?? null,
                'participant_name' => $payload['participant_name'] ?? null,
                'participant_email' => $payload['participant_email'] ?? null,
                'participant_phone' => $payload['participant_phone'] ?? null,
                'participant_birth_number' => $payload['participant_birth_number'] ?? null,
            ]);

            $event->groupDetail->increment('reserved_places');

            return $participant;
        });

        $this->eventNotificationService->dispatchMutationSignals(
            $event->fresh($this->relations()),
            EventAction::EventParticipantAdded,
            recipientEmails: [$participant->participant_email],
            context: [
                'recipient_emails' => [$participant->participant_email],
                'occurrence_display_key' => $this->resolveOccurrenceDisplayKey($event, $payload),
            ],
        );

        return $participant;
    }

    public function materializeOccurrence(Event $event, Carbon $occurrenceStartsAt, ?Carbon $occurrenceEndsAt = null, ?int $actorId = null): Event
    {
        if (! $event->is_recurring || $event->recurrence_parent_id !== null) {
            return $event;
        }

        $occurrenceEndsAt ??= $this->resolveOccurrenceEndsAt($event, [], $occurrenceStartsAt);

        $override = $this->recurrenceOverrideService->upsertOverride($event, $occurrenceStartsAt, $occurrenceEndsAt, [
            'starts_at' => $occurrenceStartsAt,
            'ends_at' => $occurrenceEndsAt,
            'status' => $event->status,
        ], $actorId);

        if (! $override->bookingDetail && ! $override->availabilityRuleDetail && ! $override->groupDetail) {
            $this->duplicateDetails($event, $override);
        }

        if ($override->services()->count() === 0) {
            $this->duplicateServices($event, $override);
        }

        return $override->fresh($this->relations());
    }

    public function removeGroupParticipant(Event $event, GroupEventParticipant $participant): void
    {
        if ((int) $participant->event_id !== (int) $event->id) {
            throw ValidationException::withMessages([
                'participant_id' => 'Ucastnik nepatri k eventu.',
            ]);
        }

        DB::transaction(function () use ($event, $participant): void {
            $participant->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            if ($event->groupDetail && $event->groupDetail->reserved_places > 0) {
                $event->groupDetail->decrement('reserved_places');
            }
        });

        $this->eventNotificationService->dispatchMutationSignals(
            $event->fresh($this->relations()),
            EventAction::EventParticipantRemoved,
            recipientEmails: [$participant->participant_email],
            context: [
                'recipient_emails' => [$participant->participant_email],
            ],
        );
    }

    private function notificationSnapshot(Event $event): array
    {
        $event->loadMissing(['services', 'branch', 'groupDetail']);

        return [
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'service_names' => $event->services->pluck('name')->filter()->values()->all(),
            'branch_name' => $event->branch?->name,
            'staff_name' => data_get($event->metadata, 'staff_name'),
            'group_title' => $event->title ?: $event->groupDetail?->service_name,
            'capacity' => $event->groupDetail?->capacity,
        ];
    }

    private function notificationEventSnapshot(Event $event): array
    {
        $event->loadMissing($this->relations());

        return [
            'id' => (int) $event->id,
            'branch_id' => (int) $event->branch_id,
            'root_event_id' => (int) ($event->root_event_id ?? $event->id),
            'type' => $event->type->value,
            'status' => (string) $event->status,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'is_recurring' => (bool) $event->is_recurring,
            'recurrence_rule' => $event->recurrence_rule,
            'metadata' => $event->metadata,
            'branch' => [
                'id' => (int) ($event->branch?->id ?? $event->branch_id),
                'name' => $event->branch?->name,
                'notification_settings' => $event->branch?->notification_settings,
            ],
            'services' => $event->services
                ->map(fn ($service) => [
                    'id' => (int) $service->id,
                    'name' => $service->name,
                ])
                ->values()
                ->all(),
            'booking_detail' => $event->bookingDetail ? [
                'id' => (int) $event->bookingDetail->id,
                'event_id' => (int) $event->id,
                'patient_id' => $event->bookingDetail->patient_id,
                'patient_name' => $event->bookingDetail->patient_name,
                'patient_email' => $event->bookingDetail->patient_email,
                'patient_phone' => $event->bookingDetail->patient_phone,
                'patient_birth_number' => $event->bookingDetail->patient_birth_number,
                'booking_status' => $event->bookingDetail->booking_status,
            ] : null,
            'group_detail' => $event->groupDetail ? [
                'id' => (int) $event->groupDetail->id,
                'event_id' => (int) $event->id,
                'service_id' => $event->groupDetail->service_id,
                'service_name' => $event->groupDetail->service_name,
                'capacity' => $event->groupDetail->capacity,
                'reserved_places' => $event->groupDetail->reserved_places,
                'group_status' => $event->groupDetail->group_status,
            ] : null,
            'participants' => $event->participants
                ->map(fn ($participant) => [
                    'id' => (int) $participant->id,
                    'event_id' => (int) $event->id,
                    'patient_id' => $participant->patient_id,
                    'status' => $participant->status,
                    'participant_name' => $participant->participant_name,
                    'participant_email' => $participant->participant_email,
                    'participant_phone' => $participant->participant_phone,
                    'participant_birth_number' => $participant->participant_birth_number,
                ])
                ->values()
                ->all(),
        ];
    }

    private function resolveOccurrenceDisplayKey(Event $event, array $payload): ?string
    {
        $occurrenceStartsAt = $this->resolveOccurrenceStartsAt($event, $payload);

        if (! $occurrenceStartsAt) {
            return null;
        }

        $rootEventId = (int) ($event->root_event_id ?? $event->id);

        return sprintf('%d:%s', $rootEventId, $occurrenceStartsAt->copy()->utc()->format('Y-m-d\TH:i:s'));
    }

    public function convertAppointmentRequest(Branch $branch, AppointmentRequest $request, array $payload, ?int $actorId = null): Event
    {
        if ((int) $request->branch_id !== (int) $branch->id) {
            throw ValidationException::withMessages([
                'appointment_request_id' => 'Request nepatri branchi.',
            ]);
        }

        if (! filled($payload['patient_id'] ?? null)) {
            throw ValidationException::withMessages([
                'patient_id' => 'Pre potvrdenie žiadosti je potrebné vybrať pacienta.',
            ]);
        }

        $services = $request->services
            ->map(fn ($service) => [
                'service_id' => $service->id,
                'duration_minutes_snapshot' => (int) ($service->duration_minutes ?? 0),
                'price_snapshot' => $service->self_pay_amount,
                'quantity' => 1,
            ])
            ->values()
            ->all();

        $event = $this->create($branch, [
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => $payload['starts_at'],
            'ends_at' => $payload['ends_at'] ?? Carbon::parse($payload['starts_at'])->addMinutes(max(15, (int) $request->total_duration_minutes)),
            'timezone' => $payload['timezone'] ?? config('app.timezone'),
            'title' => 'Rezervacia',
            'services' => $services,
            'booking_detail' => [
                'patient_id' => (int) $payload['patient_id'],
                'source_request_id' => $request->id,
                'booking_source' => 'appointment_request',
                'booking_status' => 'confirmed',
                'patient_name' => $payload['patient_name'] ?? $request->patient_name,
                'patient_email' => $payload['patient_email'] ?? $request->patient_email,
                'patient_phone' => $payload['patient_phone'] ?? $request->patient_phone,
                'patient_birth_number' => $payload['patient_birth_number'] ?? $request->patient_birth_number,
                'public_notes' => $request->patient_note,
            ],
            'metadata' => [
                'appointment_request_id' => $request->id,
            ],
        ], $actorId);

        $request->update([
            'status' => AppointmentRequest::STATUS_ACCEPTED,
            'patient_id' => (int) $payload['patient_id'],
            'accepted_booking_id' => $event->id,
            'booking_id' => null,
        ]);

        return $event;
    }

    private function updateRecurringOccurrence(Event $seriesEvent, array $payload, ?int $actorId, string $scope): Event
    {
        $occurrenceStartsAt = $this->resolveOccurrenceStartsAt($seriesEvent, $payload);
        $occurrenceEndsAt = $this->resolveOccurrenceEndsAt($seriesEvent, $payload, $occurrenceStartsAt);

        if (! $occurrenceStartsAt || ! $occurrenceEndsAt) {
            throw ValidationException::withMessages([
                'occurrence_starts_at' => 'Occurrence start is required for recurring occurrence edits.',
            ]);
        }

        if ($scope === 'this') {
            $child = $this->recurrenceOverrideService->upsertOverride($seriesEvent, $occurrenceStartsAt, $occurrenceEndsAt, $payload, $actorId);

            if (! $child->relationLoaded('bookingDetail') && ! $child->bookingDetail && ! $child->availabilityRuleDetail && ! $child->groupDetail) {
                $this->duplicateDetails($seriesEvent, $child);
            }

            if ($child->services()->count() === 0) {
                $this->duplicateServices($seriesEvent, $child);
            }

            $this->applyPayloadToEvent($child, $payload, $actorId);

            return $child;
        }

        if ($scope === 'this_and_following') {
            $payload = $this->normalizeThisAndFollowingWeekdayMovePayload($seriesEvent, $payload, $occurrenceStartsAt);

            if ($seriesEvent->starts_at?->equalTo($occurrenceStartsAt)) {
                $this->applyPayloadToEvent($seriesEvent, $payload, $actorId);

                return $seriesEvent;
            }

            $splitResult = $this->recurringEventSplitService->split($seriesEvent, $occurrenceStartsAt, $occurrenceEndsAt, $payload, $actorId);
            $newSeries = $splitResult['new_root'];

            $this->duplicateDetails($seriesEvent, $newSeries);
            $this->duplicateServices($seriesEvent, $newSeries);

            $this->applyPayloadToEvent($newSeries, $payload, $actorId);

            return $newSeries;
        }

        return $seriesEvent;
    }

    private function validateRecurringMutation(Event $event, array $payload, string $scope): void
    {
        if (! $event->is_recurring) {
            return;
        }

        if (! in_array($scope, ['this', 'this_and_following', 'series'], true)) {
            throw ValidationException::withMessages([
                'scope' => 'Recurring events require an explicit scope.',
            ]);
        }

        $occurrenceStartsAt = in_array($scope, ['this', 'this_and_following'], true)
            ? $this->resolveOccurrenceStartsAt($event, $payload)
            : null;

        if (in_array($scope, ['this', 'this_and_following'], true) && ! $occurrenceStartsAt) {
            throw ValidationException::withMessages([
                'occurrence_starts_at' => 'Occurrence start is required for this or this_and_following scope.',
            ]);
        }

        if ($occurrenceStartsAt && ! $this->occurrenceBelongsToSeries($event, $occurrenceStartsAt)) {
            throw ValidationException::withMessages([
                'occurrence_starts_at' => 'Occurrence does not belong to the selected recurring series anymore.',
            ]);
        }

        if ($scope === 'this' && Arr::exists($payload, 'recurrence_rule')) {
            throw ValidationException::withMessages([
                'recurrence_rule' => 'Recurrence rule cannot be changed for a single occurrence.',
            ]);
        }
    }

    private function occurrenceBelongsToSeries(Event $event, Carbon $occurrenceStartsAt): bool
    {
        if (! $event->is_recurring || empty($event->recurrence_rule) || ! $event->starts_at) {
            return false;
        }

        $matchingOccurrence = $this->eventOccurrenceService
            ->getOccurrenceDates(
                $event,
                $occurrenceStartsAt->copy()->startOfDay(),
                $occurrenceStartsAt->copy()->endOfDay(),
            )
            ->first(function (Carbon $occurrenceDate) use ($event, $occurrenceStartsAt): bool {
                $expectedStart = $this->combineOccurrenceDate($event->starts_at, $occurrenceDate);

                return $expectedStart?->equalTo($occurrenceStartsAt) ?? false;
            });

        return $matchingOccurrence !== null;
    }

    private function applyPayloadToEvent(Event $event, array $payload, ?int $actorId): Event
    {
        $startsAt = Arr::exists($payload, 'starts_at') && filled($payload['starts_at'])
            ? Carbon::parse($payload['starts_at'])
            : $event->starts_at;

        $endsAt = Arr::exists($payload, 'ends_at') && filled($payload['ends_at'])
            ? Carbon::parse($payload['ends_at'])
            : $event->ends_at;

        if (
            (Arr::exists($payload, 'starts_at') || Arr::exists($payload, 'ends_at') || Arr::exists($payload, 'services'))
            && $startsAt
            && $endsAt
            && $this->shouldEnforceServiceDuration($event->type)
        ) {
            $this->validateWindowMeetsServiceDuration(
                $startsAt,
                $endsAt,
                $this->resolveRequiredServiceDurationMinutes($event, $payload),
            );
        }

        if (Arr::exists($payload, 'starts_at') || Arr::exists($payload, 'ends_at')) {
            $branch = Branch::query()->find($event->branch_id);

            if ($branch) {
                if ($startsAt && $endsAt) {
                    $this->validateEventWindowIsAllowed($branch, $startsAt, $endsAt);
                }
            }
        }

        $event->fill([
            'starts_at' => isset($payload['starts_at']) ? Carbon::parse($payload['starts_at']) : $event->starts_at,
            'ends_at' => isset($payload['ends_at']) ? Carbon::parse($payload['ends_at']) : $event->ends_at,
            'status' => $payload['status'] ?? $event->status,
            'title' => $payload['title'] ?? $event->title,
            'description' => Arr::exists($payload, 'description') ? $payload['description'] : $event->description,
            'timezone' => $payload['timezone'] ?? $event->timezone,
            'recurrence_rule' => Arr::exists($payload, 'recurrence_rule') ? $payload['recurrence_rule'] : $event->recurrence_rule,
            'is_recurring' => Arr::exists($payload, 'recurrence_rule')
                ? ! empty($payload['recurrence_rule'])
                : $event->is_recurring,
            'metadata' => Arr::exists($payload, 'metadata') ? $payload['metadata'] : $event->metadata,
            'updated_by' => $actorId,
        ]);

        $event->save();

        $this->persistTypeDetails($event, $payload);

        if (Arr::exists($payload, 'services')) {
            $this->syncServices($event, $payload['services'] ?? []);
        }

        return $event;
    }

    private function normalizePayloadForUpdate(Event $event, array $payload): array
    {
        $this->assertNoStaffIdentifierPayload($payload);
        $this->assertImmutableFieldsAreNotMutated($event, $payload);

        if ($this->shouldRejectManualEndsAtMutation($event, $payload)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Koniec terminu sa pri rezervacii a skupinovom termine odvodzuje automaticky zo sluzieb.',
            ]);
        }

        if (! $this->shouldAutoDeriveEndsAt($event, $payload)) {
            return $payload;
        }

        $startsAt = Arr::exists($payload, 'starts_at') && filled($payload['starts_at'])
            ? Carbon::parse($payload['starts_at'])
            : $event->starts_at;

        if (! $startsAt) {
            return $payload;
        }

        $requiredMinutes = $this->resolveRequiredServiceDurationMinutes($event, $payload);

        if ($requiredMinutes <= 0 && $event->starts_at && $event->ends_at) {
            $requiredMinutes = max(1, $event->starts_at->diffInMinutes($event->ends_at, false));
        }

        if ($requiredMinutes <= 0) {
            return $payload;
        }

        $payload['ends_at'] = $startsAt->copy()->addMinutes($requiredMinutes);

        return $payload;
    }

    private function shouldAutoDeriveEndsAt(Event $event, array $payload): bool
    {
        if (! $this->shouldEnforceServiceDuration($event->type)) {
            return false;
        }

        return Arr::exists($payload, 'starts_at') || Arr::exists($payload, 'services');
    }

    private function shouldRejectManualEndsAtMutation(Event $event, array $payload): bool
    {
        if (! $this->shouldEnforceServiceDuration($event->type)) {
            return false;
        }

        return Arr::exists($payload, 'ends_at')
            && ! Arr::exists($payload, 'starts_at')
            && ! Arr::exists($payload, 'services');
    }

    private function assertImmutableFieldsAreNotMutated(Event $event, array $payload): void
    {
        if (Arr::exists($payload, 'type') && (string) $payload['type'] !== $event->type->value) {
            throw ValidationException::withMessages([
                'type' => 'Typ udalosti sa neda zmenit.',
            ]);
        }

        if (Arr::exists($payload, 'event_type') && (string) $payload['event_type'] !== $event->type->value) {
            throw ValidationException::withMessages([
                'event_type' => 'Typ udalosti sa neda zmenit.',
            ]);
        }

        if ($event->type !== EventType::Booking) {
            return;
        }

        if (! Arr::has($payload, 'booking_detail.patient_id')) {
            return;
        }

        $existingPatientId = (int) ($event->bookingDetail?->patient_id ?? 0);
        $requestedPatientId = (int) data_get($payload, 'booking_detail.patient_id');

        if ($existingPatientId > 0 && $requestedPatientId !== $existingPatientId) {
            throw ValidationException::withMessages([
                'booking_detail.patient_id' => 'Pacienta existujúcej rezervácie nie je možné zmeniť.',
            ]);
        }
    }

    private function assertNoStaffIdentifierPayload(array $payload): void
    {
        $forbiddenPath = $this->findForbiddenKeyPath($payload, ['staff_id']);

        if (! $forbiddenPath) {
            return;
        }

        throw ValidationException::withMessages([
            'staff_id' => 'Tento kalendar nepodporuje staff_id.',
        ]);
    }

    private function findForbiddenKeyPath(array $payload, array $forbiddenKeys, string $prefix = ''): ?string
    {
        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (in_array((string) $key, $forbiddenKeys, true)) {
                return $path;
            }

            if (! is_array($value)) {
                continue;
            }

            $nestedPath = $this->findForbiddenKeyPath($value, $forbiddenKeys, $path);

            if ($nestedPath) {
                return $nestedPath;
            }
        }

        return null;
    }

    private function resolveOccurrenceStartsAt(Event $event, array $payload): ?Carbon
    {
        if (filled($payload['occurrence_starts_at'] ?? null)) {
            return Carbon::parse($payload['occurrence_starts_at']);
        }

        if (filled($payload['occurrence_date'] ?? null) && $event->starts_at) {
            return Carbon::parse(Carbon::parse($payload['occurrence_date'])->toDateString() . ' ' . $event->starts_at->format('H:i:s'));
        }

        return null;
    }

    private function resolveOccurrenceEndsAt(Event $event, array $payload, ?Carbon $occurrenceStartsAt): ?Carbon
    {
        if (filled($payload['occurrence_ends_at'] ?? null)) {
            return Carbon::parse($payload['occurrence_ends_at']);
        }

        if (! $occurrenceStartsAt || ! $event->starts_at || ! $event->ends_at) {
            return null;
        }

        $durationMinutes = max(0, $event->starts_at->diffInMinutes($event->ends_at, false));

        return $occurrenceStartsAt->copy()->addMinutes($durationMinutes);
    }

    private function combineOccurrenceDate(?Carbon $source, Carbon $occurrenceDate): ?Carbon
    {
        if (! $source) {
            return null;
        }

        return Carbon::parse($occurrenceDate->toDateString() . ' ' . $source->format('H:i:s'));
    }

    private function persistTypeDetails(Event $event, array $payload): void
    {
        if ($event->type === EventType::Booking) {
            $detailPayload = array_merge([
                'booking_status' => $event->status,
            ], $payload['booking_detail'] ?? []);

            $event->bookingDetail()->updateOrCreate(
                ['event_id' => $event->id],
                $detailPayload,
            );

            return;
        }

        if ($event->type === EventType::AvailabilityRule) {
            $event->availabilityRuleDetail()->updateOrCreate(
                ['event_id' => $event->id],
                $payload['availability_rule_detail'] ?? [],
            );

            return;
        }

        if ($event->type === EventType::GroupEvent) {
            $existingGroupDetail = $event->groupDetail()->first();

            $detailPayload = array_merge([
                'capacity' => $existingGroupDetail?->capacity ?? 1,
                'reserved_places' => $existingGroupDetail?->reserved_places ?? 0,
                'group_status' => $existingGroupDetail?->group_status ?? $event->status,
            ], $payload['group_detail'] ?? []);

            $event->groupDetail()->updateOrCreate(
                ['event_id' => $event->id],
                $detailPayload,
            );
        }
    }

    private function syncServices(Event $event, array $servicePayload): void
    {
        $serviceIds = collect($servicePayload)
            ->pluck('service_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($serviceIds->isEmpty()) {
            $event->services()->sync([]);

            return;
        }

        $services = Service::query()
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');

        $syncPayload = [];

        foreach ($servicePayload as $index => $item) {
            $serviceId = (int) ($item['service_id'] ?? 0);

            if (! isset($services[$serviceId])) {
                continue;
            }

            $service = $services[$serviceId];

            $syncPayload[$serviceId] = [
                'duration_minutes_snapshot' => $item['duration_minutes_snapshot'] ?? $service->duration_minutes,
                'price_snapshot' => $item['price_snapshot'] ?? $service->self_pay_amount,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
            ];
        }

        $event->services()->sync($syncPayload);
    }

    private function duplicateDetails(Event $source, Event $target): void
    {
        if ($source->bookingDetail) {
            $detail = $source->bookingDetail->replicate();
            $detail->event_id = $target->id;
            $detail->save();
        }

        if ($source->availabilityRuleDetail) {
            $detail = $source->availabilityRuleDetail->replicate();
            $detail->event_id = $target->id;
            $detail->save();
        }

        if ($source->groupDetail) {
            $detail = $source->groupDetail->replicate();
            $detail->event_id = $target->id;
            $detail->reserved_places = 0;
            $detail->save();
        }
    }

    private function duplicateServices(Event $source, Event $target): void
    {
        $payload = $source->services
            ->map(fn ($service) => [
                'service_id' => $service->id,
                'duration_minutes_snapshot' => $service->pivot?->duration_minutes_snapshot,
                'price_snapshot' => $service->pivot?->price_snapshot,
                'sort_order' => $service->pivot?->sort_order,
                'quantity' => $service->pivot?->quantity,
            ])
            ->values()
            ->all();

        $this->syncServices($target, $payload);
    }

    private function validatePayload(EventType $type, array $payload): void
    {
        if (! filled($payload['starts_at'] ?? null) || ! filled($payload['ends_at'] ?? null)) {
            throw ValidationException::withMessages([
                'starts_at' => 'starts_at a ends_at su povinne.',
            ]);
        }

        if ($type === EventType::Booking) {
            if (! filled(data_get($payload, 'booking_detail.patient_name'))) {
                throw ValidationException::withMessages([
                    'booking_detail.patient_name' => 'Pre booking event je patient_name povinny.',
                ]);
            }

            $bookingStatus = (string) ($payload['status'] ?? data_get($payload, 'booking_detail.booking_status', 'confirmed'));

            if ($bookingStatus !== 'draft' && ! filled(data_get($payload, 'booking_detail.patient_id'))) {
                throw ValidationException::withMessages([
                    'booking_detail.patient_id' => 'Pre potvrdenú rezerváciu je patient_id povinný.',
                ]);
            }

            if (empty($payload['services'])) {
                throw ValidationException::withMessages([
                    'services' => 'Pre booking event je aspon jedna sluzba povinna.',
                ]);
            }
        }

        if ($type === EventType::AvailabilityRule && empty($payload['services'])) {
            throw ValidationException::withMessages([
                'services' => 'Pre availability_rule event je aspon jedna povolena sluzba povinna.',
            ]);
        }

        if ($type === EventType::GroupEvent) {
            if (! filled(data_get($payload, 'group_detail.capacity'))) {
                throw ValidationException::withMessages([
                    'group_detail.capacity' => 'Pre group_event je capacity povinna.',
                ]);
            }

            if ((int) data_get($payload, 'group_detail.capacity') < 1) {
                throw ValidationException::withMessages([
                    'group_detail.capacity' => 'Kapacita musi byt aspon 1.',
                ]);
            }
        }
    }

    private function relations(): array
    {
        return [
            'services',
            'bookingDetail',
            'availabilityRuleDetail',
            'groupDetail',
            'participants',
        ];
    }

    private function validateStartDateIsOpen(Branch $branch, Carbon $startsAt): void
    {
        if (! $this->disabledDayService->isDisabled($branch, $startsAt)) {
            return;
        }

        throw ValidationException::withMessages([
            'starts_at' => 'Tento deň je v kalendári zakázaný.',
        ]);
    }

    private function validateEventWindowIsAllowed(Branch $branch, Carbon $startsAt, Carbon $endsAt): void
    {
        $this->validateStartDateIsOpen($branch, $startsAt);

        if ($this->openingHoursService->isWithinOpeningHours($branch, $startsAt, $endsAt)) {
            return;
        }

        throw ValidationException::withMessages([
            'starts_at' => 'Termín musí byť v rámci otváracích hodín.',
        ]);
    }

    private function shouldEnforceServiceDuration(EventType $type): bool
    {
        return in_array($type, [EventType::Booking, EventType::GroupEvent], true);
    }

    private function validateWindowMeetsServiceDuration(Carbon $startsAt, Carbon $endsAt, int $requiredMinutes): void
    {
        if ($requiredMinutes <= 0) {
            return;
        }

        $actualMinutes = max(0, $startsAt->diffInMinutes($endsAt, false));

        if ($actualMinutes >= $requiredMinutes) {
            return;
        }

        throw ValidationException::withMessages([
            'ends_at' => "Trvanie terminu nemoze byt kratsie ako trvanie sluzieb ({$requiredMinutes} min).",
        ]);
    }

    private function resolveRequiredServiceDurationMinutes(?Event $event, array $payload): int
    {
        if (Arr::exists($payload, 'services')) {
            return $this->resolveDurationFromServicePayload($payload['services'] ?? []);
        }

        if (! $event) {
            return 0;
        }

        $event->loadMissing('services');

        return (int) $event->services
            ->sum(function ($service): int {
                $durationMinutes = (int) ($service->pivot?->duration_minutes_snapshot ?? $service->duration_minutes ?? 0);
                $quantity = max(1, (int) ($service->pivot?->quantity ?? 1));

                return max(0, $durationMinutes) * $quantity;
            });
    }

    private function resolveDurationFromServicePayload(array $services): int
    {
        $serviceItems = collect($services)
            ->filter(fn ($item) => filled($item['service_id'] ?? null))
            ->values();

        if ($serviceItems->isEmpty()) {
            return 0;
        }

        $serviceIds = $serviceItems
            ->pluck('service_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $serviceMap = Service::query()
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');

        return (int) $serviceItems
            ->sum(function ($item) use ($serviceMap): int {
                $serviceId = (int) ($item['service_id'] ?? 0);
                $durationMinutes = (int) ($item['duration_minutes_snapshot'] ?? $serviceMap[$serviceId]?->duration_minutes ?? 0);
                $quantity = max(1, (int) ($item['quantity'] ?? 1));

                return max(0, $durationMinutes) * $quantity;
            });
    }

    /**
     * @return array{event: Event, affected_event_ids: array<int>}
     */
    private function updateLogicalSeries(Event $event, array $payload, ?int $actorId): array
    {
        $masters = $this->resolveLogicalSeriesMasters($event);

        if ($masters->isEmpty()) {
            $updated = $this->applyPayloadToEvent($event, $payload, $actorId)->fresh($this->relations());

            return [
                'event' => $updated,
                'affected_event_ids' => [$updated->id],
            ];
        }

        $affectedEventIds = [];

        foreach ($masters as $master) {
            $masterPayload = $this->normalizeSeriesWeekdayMovePayloadForMaster($event, $master, $payload);
            $masterPayload = $this->normalizeSeriesTimePayloadForEvent($master, $masterPayload);
            $this->applyPayloadToEvent($master, $masterPayload, $actorId);
            $affectedEventIds[] = (int) $master->id;

            $affectedEventIds = [
                ...$affectedEventIds,
                ...$this->syncTimeExceptionsForSeriesMaster($master, $payload, $actorId),
            ];
        }

        $primary = $masters->firstWhere('id', $event->id) ?? $masters->first();
        $updatedPrimary = $primary?->fresh($this->relations()) ?? $event->fresh($this->relations());

        return [
            'event' => $updatedPrimary,
            'affected_event_ids' => collect($affectedEventIds)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function hasTimeMutationPayload(array $payload): bool
    {
        return Arr::exists($payload, 'starts_at') || Arr::exists($payload, 'ends_at');
    }

    private function normalizeSeriesWeekdayMovePayloadForMaster(Event $selectedEvent, Event $master, array $payload): array
    {
        if (Arr::exists($payload, 'recurrence_rule') || ! filled($payload['starts_at'] ?? null)) {
            return $payload;
        }

        if (! $this->isWeeklyRecurrenceRule($master->recurrence_rule)) {
            return $payload;
        }

        $selectedOccurrenceStartsAt = $this->resolveOccurrenceStartsAt($selectedEvent, $payload) ?? $selectedEvent->starts_at;

        if (! $selectedOccurrenceStartsAt) {
            return $payload;
        }

        $newStartsAt = Carbon::parse($payload['starts_at']);

        if ($this->weekdayCode($selectedOccurrenceStartsAt) === $this->weekdayCode($newStartsAt)) {
            return $payload;
        }

        $normalized = $payload;
        $normalized['recurrence_rule'] = $this->replaceWeekdayInRuleForMove(
            $master->recurrence_rule ?? [],
            $this->weekdayCode($selectedOccurrenceStartsAt),
            $this->weekdayCode($newStartsAt),
            false,
        );

        return $normalized;
    }

    private function normalizeThisAndFollowingWeekdayMovePayload(Event $seriesEvent, array $payload, ?Carbon $occurrenceStartsAt): array
    {
        if (! $occurrenceStartsAt || Arr::exists($payload, 'recurrence_rule') || ! filled($payload['starts_at'] ?? null)) {
            return $payload;
        }

        if (! $this->isWeeklyRecurrenceRule($seriesEvent->recurrence_rule)) {
            return $payload;
        }

        $newStartsAt = Carbon::parse($payload['starts_at']);
        $selectedWeekday = $this->weekdayCode($occurrenceStartsAt);
        $newWeekday = $this->weekdayCode($newStartsAt);

        if ($selectedWeekday === $newWeekday) {
            return $payload;
        }

        $this->assertThisAndFollowingWeekdayMoveIsAllowed($seriesEvent, $occurrenceStartsAt, $newStartsAt, $selectedWeekday, $newWeekday);

        $normalized = $payload;
        $normalized['recurrence_rule'] = $this->replaceWeekdayInRuleForMove(
            $seriesEvent->recurrence_rule ?? [],
            $selectedWeekday,
            $newWeekday,
            true,
        );

        return $normalized;
    }

    private function assertThisAndFollowingWeekdayMoveIsAllowed(
        Event $seriesEvent,
        Carbon $occurrenceStartsAt,
        Carbon $newStartsAt,
        string $selectedWeekday,
        string $newWeekday,
    ): void {
        $weekdays = collect(data_get($seriesEvent->recurrence_rule, 'weekdays', []))
            ->map(fn ($weekday) => strtoupper((string) $weekday))
            ->filter(fn (string $weekday) => in_array($weekday, ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'], true))
            ->unique()
            ->values();

        if ($weekdays->isEmpty()) {
            $weekdays = collect([$selectedWeekday]);
        }

        if ($selectedWeekday !== $newWeekday && $weekdays->contains($newWeekday)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Cannot move this_and_following to a weekday that already exists in recurrence weekdays.',
            ]);
        }

        $lastRetainedOccurrenceStartAt = $this->lastRetainedOccurrenceStartAt($seriesEvent, $occurrenceStartsAt);

        if ($lastRetainedOccurrenceStartAt && $newStartsAt->lte($lastRetainedOccurrenceStartAt)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Cannot move this_and_following occurrence before or overlapping retained occurrences of the old segment.',
            ]);
        }
    }

    private function lastRetainedOccurrenceStartAt(Event $seriesEvent, Carbon $occurrenceStartsAt): ?Carbon
    {
        if (! $seriesEvent->starts_at) {
            return null;
        }

        $previousOccurrenceDate = $this->eventOccurrenceService
            ->getOccurrenceDates(
                $seriesEvent,
                $seriesEvent->starts_at->copy()->startOfDay(),
                $occurrenceStartsAt->copy()->subDay()->endOfDay(),
            )
            ->last();

        if (! $previousOccurrenceDate) {
            return null;
        }

        return $this->combineOccurrenceDate($seriesEvent->starts_at, $previousOccurrenceDate);
    }

    private function isWeeklyRecurrenceRule(mixed $rule): bool
    {
        return is_array($rule) && ((string) data_get($rule, 'frequency', 'weekly')) === 'weekly';
    }

    private function replaceWeekdayInRuleForMove(array $rule, string $selectedWeekday, string $newWeekday, bool $strictConflict): array
    {
        $weekdays = collect(data_get($rule, 'weekdays', []))
            ->map(fn ($weekday) => strtoupper((string) $weekday))
            ->filter(fn (string $weekday) => in_array($weekday, ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'], true))
            ->unique()
            ->values();

        if ($weekdays->isEmpty()) {
            $weekdays = collect([$selectedWeekday]);
        }

        if ($strictConflict && $selectedWeekday !== $newWeekday && $weekdays->contains($newWeekday)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Cannot move this_and_following to a weekday that already exists in recurrence weekdays.',
            ]);
        }

        $updatedWeekdays = $weekdays
            ->map(fn (string $weekday): string => $weekday === $selectedWeekday ? $newWeekday : $weekday)
            ->unique()
            ->values()
            ->all();

        if ($updatedWeekdays === []) {
            $updatedWeekdays = [$newWeekday];
        }

        $updatedRule = $rule;
        data_set($updatedRule, 'weekdays', $updatedWeekdays);

        return $updatedRule;
    }

    private function weekdayCode(Carbon $value): string
    {
        return match ((int) $value->dayOfWeekIso) {
            1 => 'MO',
            2 => 'TU',
            3 => 'WE',
            4 => 'TH',
            5 => 'FR',
            6 => 'SA',
            default => 'SU',
        };
    }

    private function normalizeSeriesTimePayloadForEvent(Event $event, array $payload): array
    {
        if (! $this->hasTimeMutationPayload($payload)) {
            return $payload;
        }

        $window = $this->remapWindowToEventDate($event, $payload);
        $normalized = $payload;

        if (Arr::exists($payload, 'starts_at')) {
            $normalized['starts_at'] = $window['starts_at'];
        }

        if (Arr::exists($payload, 'ends_at')) {
            $normalized['ends_at'] = $window['ends_at'];
        }

        return $normalized;
    }

    /**
     * @return array{starts_at: ?Carbon, ends_at: ?Carbon}
     */
    private function remapWindowToEventDate(Event $event, array $payload): array
    {
        $currentStartsAt = $event->starts_at;
        $currentEndsAt = $event->ends_at;

        if (! $currentStartsAt || ! $currentEndsAt) {
            return [
                'starts_at' => Arr::exists($payload, 'starts_at') && filled($payload['starts_at'])
                    ? Carbon::parse($payload['starts_at'])
                    : $currentStartsAt,
                'ends_at' => Arr::exists($payload, 'ends_at') && filled($payload['ends_at'])
                    ? Carbon::parse($payload['ends_at'])
                    : $currentEndsAt,
            ];
        }

        $hasStartsAt = Arr::exists($payload, 'starts_at') && filled($payload['starts_at']);
        $hasEndsAt = Arr::exists($payload, 'ends_at') && filled($payload['ends_at']);

        $requestedStartsAt = $hasStartsAt ? Carbon::parse($payload['starts_at']) : null;
        $requestedEndsAt = $hasEndsAt ? Carbon::parse($payload['ends_at']) : null;

        $remappedStartsAt = $currentStartsAt->copy();
        $remappedEndsAt = $currentEndsAt->copy();

        if ($requestedStartsAt) {
            $remappedStartsAt = Carbon::parse($currentStartsAt->toDateString() . ' ' . $requestedStartsAt->format('H:i:s'));
        }

        if ($requestedStartsAt && $requestedEndsAt) {
            $durationMinutes = max(0, $requestedStartsAt->diffInMinutes($requestedEndsAt, false));
            $remappedEndsAt = $remappedStartsAt->copy()->addMinutes($durationMinutes);
        } elseif ($requestedStartsAt) {
            $durationMinutes = max(0, $currentStartsAt->diffInMinutes($currentEndsAt, false));
            $remappedEndsAt = $remappedStartsAt->copy()->addMinutes($durationMinutes);
        } elseif ($requestedEndsAt) {
            $remappedEndsAt = Carbon::parse($currentEndsAt->toDateString() . ' ' . $requestedEndsAt->format('H:i:s'));
        }

        return [
            'starts_at' => $remappedStartsAt,
            'ends_at' => $remappedEndsAt,
        ];
    }

    /**
     * @return array<int>
     */
    private function syncTimeExceptionsForSeriesMaster(Event $master, array $payload, ?int $actorId): array
    {
        if (! $this->hasTimeMutationPayload($payload)) {
            return [];
        }

        $affectedChildIds = [];

        $master->recurrenceChildren()
            ->whereNull('deleted_at')
            ->get()
            ->each(function (Event $child) use ($payload, $actorId, &$affectedChildIds): void {
                $window = $this->remapWindowToEventDate($child, $payload);

                $originalStartsAt = $child->recurrence_original_starts_at;
                $originalEndsAt = $child->recurrence_original_ends_at;

                if ($originalStartsAt && $originalEndsAt) {
                    $originalWindow = $this->remapWindowFromAnchors($originalStartsAt, $originalEndsAt, $payload);
                    $child->recurrence_original_starts_at = $originalWindow['starts_at'];
                    $child->recurrence_original_ends_at = $originalWindow['ends_at'];
                }

                if ($child->status === 'cancelled') {
                    $child->updated_by = $actorId;
                    $child->save();
                    $affectedChildIds[] = (int) $child->id;

                    return;
                }

                $child->starts_at = $window['starts_at'];
                $child->ends_at = $window['ends_at'];
                $child->updated_by = $actorId;
                $child->save();

                $affectedChildIds[] = (int) $child->id;
            });

        return $affectedChildIds;
    }

    /**
     * @return array{starts_at: Carbon, ends_at: Carbon}
     */
    private function remapWindowFromAnchors(Carbon $currentStartsAt, Carbon $currentEndsAt, array $payload): array
    {
        $hasStartsAt = Arr::exists($payload, 'starts_at') && filled($payload['starts_at']);
        $hasEndsAt = Arr::exists($payload, 'ends_at') && filled($payload['ends_at']);

        $requestedStartsAt = $hasStartsAt ? Carbon::parse($payload['starts_at']) : null;
        $requestedEndsAt = $hasEndsAt ? Carbon::parse($payload['ends_at']) : null;

        $remappedStartsAt = $currentStartsAt->copy();
        $remappedEndsAt = $currentEndsAt->copy();

        if ($requestedStartsAt) {
            $remappedStartsAt = Carbon::parse($currentStartsAt->toDateString() . ' ' . $requestedStartsAt->format('H:i:s'));
        }

        if ($requestedStartsAt && $requestedEndsAt) {
            $durationMinutes = max(0, $requestedStartsAt->diffInMinutes($requestedEndsAt, false));
            $remappedEndsAt = $remappedStartsAt->copy()->addMinutes($durationMinutes);
        } elseif ($requestedStartsAt) {
            $durationMinutes = max(0, $currentStartsAt->diffInMinutes($currentEndsAt, false));
            $remappedEndsAt = $remappedStartsAt->copy()->addMinutes($durationMinutes);
        } elseif ($requestedEndsAt) {
            $remappedEndsAt = Carbon::parse($currentEndsAt->toDateString() . ' ' . $requestedEndsAt->format('H:i:s'));
        }

        return [
            'starts_at' => $remappedStartsAt,
            'ends_at' => $remappedEndsAt,
        ];
    }

    private function resolveScopedMutationEvent(Event $event, string $scope, array $payload = []): Event
    {
        if (! in_array($scope, ['series', 'this_and_following', 'this'], true)) {
            return $event;
        }

        $seriesMaster = $event->recurrence_parent_id !== null
            ? $this->resolveSeriesRootEvent($event)
            : $event;

        if (! in_array($scope, ['this_and_following', 'this'], true)) {
            return $seriesMaster;
        }

        $occurrenceReference = $this->resolveOccurrenceReference($payload);

        if (! $occurrenceReference || ! $seriesMaster->is_recurring || empty($seriesMaster->recurrence_rule)) {
            return $seriesMaster;
        }

        return $this->resolveActiveRecurringMaster($seriesMaster, $occurrenceReference, array_key_exists('occurrence_starts_at', $payload) && filled($payload['occurrence_starts_at']))
            ?? $seriesMaster;
    }

    private function resolveActiveRecurringMaster(Event $seriesMaster, Carbon $occurrenceReference, bool $hasExactOccurrenceStart = false): ?Event
    {
        $familyMasters = $this->resolveSplitFamilyMasters($seriesMaster);

        return $familyMasters
            ->filter(function (Event $candidate) use ($occurrenceReference, $hasExactOccurrenceStart): bool {
                return $this->seriesOwnsOccurrenceReference($candidate, $occurrenceReference, $hasExactOccurrenceStart);
            })
            ->sortByDesc(function (Event $candidate): string {
                return sprintf(
                    '%010d|%s|%010d',
                    (int) ($candidate->recurrence_sequence ?? 0),
                    $candidate->starts_at?->format('Y-m-d H:i:s.u') ?? '',
                    (int) $candidate->id,
                );
            })
            ->first();
    }

    private function resolveSplitFamilyMasters(Event $seriesMaster): Collection
    {
        $baseMaster = $seriesMaster->recurrence_parent_id !== null
            ? $this->resolveSeriesRootEvent($seriesMaster)
            : $seriesMaster;

        return $this->resolveLogicalSeriesMasters($baseMaster);
    }

    private function resolveLogicalSeriesMasters(Event $event): Collection
    {
        $seriesRoot = $event->recurrence_parent_id !== null
            ? $this->resolveSeriesRootEvent($event)
            : $event;
        $logicalRootId = $this->logicalRootEventId($seriesRoot);

        $masters = Event::query()
            ->with($this->relations())
            ->where('branch_id', $seriesRoot->branch_id)
            ->where('type', $seriesRoot->type)
            ->whereNull('recurrence_parent_id')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($logicalRootId): void {
                $query->where('root_event_id', $logicalRootId)
                    ->orWhere(function ($fallback) use ($logicalRootId): void {
                        $fallback
                            ->whereNull('root_event_id')
                            ->whereKey($logicalRootId);
                    });
            })
            ->get()
            ->values();

        if ($masters->isNotEmpty()) {
            return $masters;
        }

        $seriesUuid = data_get($seriesRoot->metadata, 'series_uuid');

        if (! filled($seriesUuid)) {
            return collect([$seriesRoot]);
        }

        return Event::query()
            ->with($this->relations())
            ->where('branch_id', $seriesRoot->branch_id)
            ->where('type', $seriesRoot->type)
            ->whereNull('recurrence_parent_id')
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn (Event $candidate): bool => data_get($candidate->metadata, 'series_uuid') === $seriesUuid)
            ->values();
    }

    private function logicalRootEventId(Event $event): int
    {
        return (int) ($event->root_event_id ?? $event->id);
    }

    private function deleteThisAndFollowingAcrossLogicalSeries(Event $event, Carbon $occurrenceStartsAt): void
    {
        $masters = $this->resolveLogicalSeriesMasters($event);

        foreach ($masters as $master) {
            if (! $master->is_recurring || empty($master->recurrence_rule) || ! $master->starts_at) {
                if ($master->starts_at?->gte($occurrenceStartsAt)) {
                    $master->recurrenceChildren()->whereNull('deleted_at')->delete();
                    $master->delete();
                }

                continue;
            }

            if (! $this->seriesHasOccurrenceOnOrAfter($master, $occurrenceStartsAt)) {
                continue;
            }

            if ($this->seriesHasOccurrenceBefore($master, $occurrenceStartsAt)) {
                $this->trimSeriesBeforeOccurrence($master, $occurrenceStartsAt);

                $master->recurrenceChildren()
                    ->whereNull('deleted_at')
                    ->where('recurrence_original_starts_at', '>=', $occurrenceStartsAt)
                    ->delete();

                continue;
            }

            $master->recurrenceChildren()->whereNull('deleted_at')->delete();
            $master->delete();
        }
    }

    private function deleteLogicalSeries(Event $event): void
    {
        $masters = $this->resolveLogicalSeriesMasters($event);

        foreach ($masters as $master) {
            $master->recurrenceChildren()->whereNull('deleted_at')->delete();
            $master->delete();
        }
    }

    private function seriesHasOccurrenceOnOrAfter(Event $master, Carbon $occurrenceStartsAt): bool
    {
        return $this->eventOccurrenceService
            ->getOccurrenceDates(
                $master,
                $occurrenceStartsAt->copy()->startOfDay(),
                $this->recurrenceSearchEnd($master, $occurrenceStartsAt),
            )
            ->isNotEmpty();
    }

    private function seriesHasOccurrenceBefore(Event $master, Carbon $occurrenceStartsAt): bool
    {
        $rangeEnd = $occurrenceStartsAt->copy()->subDay()->endOfDay();

        if ($rangeEnd->lt($master->starts_at->copy()->startOfDay())) {
            return false;
        }

        return $this->eventOccurrenceService
            ->getOccurrenceDates(
                $master,
                $master->starts_at->copy()->startOfDay(),
                $rangeEnd,
            )
            ->isNotEmpty();
    }

    private function trimSeriesBeforeOccurrence(Event $master, Carbon $occurrenceStartsAt): void
    {
        $previousOccurrenceDate = $this->eventOccurrenceService
            ->getOccurrenceDates(
                $master,
                $master->starts_at->copy()->startOfDay(),
                $occurrenceStartsAt->copy()->subDay()->endOfDay(),
            )
            ->last();

        $trimmedRule = $master->recurrence_rule ?? [];
        data_set($trimmedRule, 'ends.type', 'on');
        data_set($trimmedRule, 'ends.until', $previousOccurrenceDate?->toDateString());
        data_set($trimmedRule, 'ends.count', null);

        $master->recurrence_rule = $trimmedRule;
        $master->save();
    }

    private function recurrenceSearchEnd(Event $master, Carbon $from): Carbon
    {
        $rule = $master->recurrence_rule ?? [];

        if (data_get($rule, 'ends.type') === 'on' && filled(data_get($rule, 'ends.until'))) {
            return Carbon::parse(data_get($rule, 'ends.until'))->endOfDay();
        }

        if (data_get($rule, 'ends.type') === 'after' && filled(data_get($rule, 'ends.count'))) {
            $count = max(1, (int) data_get($rule, 'ends.count'));
            $interval = max(1, (int) data_get($rule, 'interval', 1));
            $frequency = (string) data_get($rule, 'frequency', 'weekly');

            $daysToAdd = match ($frequency) {
                'daily' => ($count * $interval) + 7,
                'monthly' => ($count * $interval * 31) + 31,
                'yearly' => ($count * $interval * 366) + 366,
                default => ($count * $interval * 7) + 14,
            };

            return $from->copy()->addDays($daysToAdd)->endOfDay();
        }

        return $from->copy()->addYears(5)->endOfDay();
    }

    private function seriesOwnsOccurrenceReference(Event $seriesMaster, Carbon $occurrenceReference, bool $hasExactOccurrenceStart = false): bool
    {
        if (! $seriesMaster->is_recurring || empty($seriesMaster->recurrence_rule) || ! $seriesMaster->starts_at) {
            return false;
        }

        $occurrenceDates = $this->eventOccurrenceService->getOccurrenceDates(
            $seriesMaster,
            $occurrenceReference->copy()->startOfDay(),
            $occurrenceReference->copy()->endOfDay(),
        );

        if ($occurrenceDates->isEmpty()) {
            return false;
        }

        if (! $hasExactOccurrenceStart) {
            return true;
        }

        return $occurrenceDates->contains(function (Carbon $occurrenceDate) use ($seriesMaster, $occurrenceReference): bool {
            $candidateStart = $this->combineOccurrenceDate($seriesMaster->starts_at, $occurrenceDate);

            return $candidateStart?->equalTo($occurrenceReference) ?? false;
        });
    }

    private function resolveOccurrenceReference(array $payload): ?Carbon
    {
        if (filled($payload['occurrence_starts_at'] ?? null)) {
            return Carbon::parse($payload['occurrence_starts_at']);
        }

        if (filled($payload['occurrence_date'] ?? null)) {
            return Carbon::parse($payload['occurrence_date'])->startOfDay();
        }

        return null;
    }

    private function resolveSeriesRootEvent(Event $event): Event
    {
        $current = $event;
        $visited = [];

        while ($current->recurrence_parent_id !== null) {
            if (isset($visited[$current->id])) {
                break;
            }

            $visited[$current->id] = true;

            $parent = Event::query()
                ->with($this->relations())
                ->find($current->recurrence_parent_id);

            if (! $parent) {
                break;
            }

            $current = $parent;
        }

        return $current;
    }

    private function cascadeSeriesCancellation(Event $event, ?int $actorId): void
    {
        if (! $event->is_recurring || $event->recurrence_parent_id !== null) {
            return;
        }

        $cancelledAt = now();

        $event->recurrenceChildren()
            ->whereNull('deleted_at')
            ->get()
            ->each(function (Event $child) use ($cancelledAt, $actorId): void {
                $child->status = 'cancelled';
                $child->cancelled_at = $cancelledAt;
                $child->updated_by = $actorId;
                $child->metadata = array_merge($child->metadata ?? [], [
                    'cancelled_at' => $cancelledAt->toIso8601String(),
                ]);
                $child->save();
            });
    }
}
