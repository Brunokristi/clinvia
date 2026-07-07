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
                'metadata' => $payload['metadata'] ?? [],
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->persistTypeDetails($event, $payload);
            $this->syncServices($event, $payload['services'] ?? []);

            return $event->fresh($this->relations());
        });

        $this->eventNotificationService->dispatchMutationSignals($event, EventAction::EventCreated);

        return $event;
    }

    public function update(Event $event, array $payload, ?int $actorId = null, ?string $scope = null): Event
    {
        $scope = $this->eventOccurrenceService->resolveScope($scope)->value;
        $event = $this->resolveScopedMutationEvent($event, $scope);
        $this->validateRecurringMutation($event, $payload, $scope);

        $updatedEvent = DB::transaction(function () use ($event, $payload, $actorId, $scope): Event {
            if ($scope !== 'series' && $event->is_recurring && $this->resolveOccurrenceStartsAt($event, $payload)) {
                $event = $this->updateRecurringOccurrence($event, $payload, $actorId, $scope);

                return $event->fresh($this->relations());
            }

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
            affectedEventIds: [$updatedEvent->id],
            recurrenceScope: $scope,
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
        $event = $this->resolveScopedMutationEvent($event, $scope);

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
        $event = $this->resolveScopedMutationEvent($event, $scope);

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
                    $oldRoot = $this->recurringEventSplitService->split($event, $occurrenceStartsAt, $occurrenceEndsAt, [
                        'recurrence_rule' => null,
                        'starts_at' => $occurrenceStartsAt,
                        'ends_at' => $occurrenceEndsAt,
                    ])['old_root'];

                    Event::query()
                        ->where('split_from_event_id', $oldRoot->id)
                        ->where('starts_at', '>=', $occurrenceStartsAt)
                        ->delete();

                    return;
                }

                return;
            }

            if ($scope === 'series') {
                $event->recurrenceChildren()
                    ->whereNull('deleted_at')
                    ->delete();
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
        );
    }

    public function duplicate(Event $event, ?int $actorId = null): Event
    {
        $duplicate = DB::transaction(function () use ($event, $actorId): Event {
            $copy = $event->replicate([
                'created_at',
                'updated_at',
                'deleted_at',
                'cancelled_at',
            ]);

            $copy->status = 'confirmed';
            $copy->is_recurring = false;
            $copy->recurrence_rule = null;
            $copy->recurrence_parent_id = null;
            $copy->recurrence_exception_date = null;
            $copy->recurrence_original_starts_at = null;
            $copy->recurrence_original_ends_at = null;
            $copy->created_by = $actorId;
            $copy->updated_by = $actorId;

            $copyMetadata = $copy->metadata ?? [];
            unset($copyMetadata['recurrence_excluded_dates']);
            $copy->metadata = $copyMetadata;

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
        );
    }

    public function convertAppointmentRequest(Branch $branch, AppointmentRequest $request, array $payload, ?int $actorId = null): Event
    {
        if ((int) $request->branch_id !== (int) $branch->id) {
            throw ValidationException::withMessages([
                'appointment_request_id' => 'Request nepatri branchi.',
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
            'title' => 'Rezervacia',
            'services' => $services,
            'booking_detail' => [
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
            'status' => 'converted',
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

        if (in_array($scope, ['this', 'this_and_following'], true) && ! $this->resolveOccurrenceStartsAt($event, $payload)) {
            throw ValidationException::withMessages([
                'occurrence_starts_at' => 'Occurrence start is required for this or this_and_following scope.',
            ]);
        }

        if ($scope === 'this' && Arr::exists($payload, 'recurrence_rule')) {
            throw ValidationException::withMessages([
                'recurrence_rule' => 'Recurrence rule cannot be changed for a single occurrence.',
            ]);
        }
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

    private function resolveScopedMutationEvent(Event $event, string $scope): Event
    {
        if (! in_array($scope, ['series', 'this_and_following', 'this'], true) || $event->recurrence_parent_id === null) {
            return $event;
        }

        return $this->resolveSeriesRootEvent($event);
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
