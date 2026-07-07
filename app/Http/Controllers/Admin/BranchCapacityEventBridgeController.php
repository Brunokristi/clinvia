<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Service;
use App\Modules\Calendar\Actions\AddGroupEventParticipantAction;
use App\Modules\Calendar\Actions\CancelEventAction;
use App\Modules\Calendar\Actions\CreateEventAction;
use App\Modules\Calendar\Actions\DeleteEventAction;
use App\Modules\Calendar\Actions\RemoveGroupEventParticipantAction;
use App\Modules\Calendar\Actions\RescheduleEventAction;
use App\Modules\Calendar\Actions\UpdateEventAction;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\CalendarEntitlementService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchCapacityEventBridgeController extends Controller
{
    public function __construct(
        private readonly CalendarEntitlementService $entitlementService,
    ) {
    }

    public function store(
        Request $request,
        Branch $branch,
        CreateEventAction $createEventAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1'],
            'admin_note' => ['nullable', 'string'],
            'repeats' => ['nullable', 'boolean'],
            'repeat_every' => ['nullable', 'integer', 'min:1'],
            'repeat_unit' => ['nullable', 'in:days,weeks,months'],
            'repeat_weekdays' => ['nullable', 'array'],
            'repeat_weekdays.*' => ['in:MO,TU,WE,TH,FR,SA,SU'],
            'repeat_ends_on' => ['nullable', 'date'],
            'recurrence' => ['nullable', 'array'],
            'patients' => ['nullable', 'array'],
            'patients.*.patient_name' => ['required_with:patients', 'string', 'max:255'],
            'patients.*.patient_email' => ['nullable', 'email', 'max:255'],
            'patients.*.patient_phone' => ['nullable', 'string', 'max:255'],
            'group_patients' => ['nullable', 'array'],
            'group_patients.*.patient_name' => ['required_with:group_patients', 'string', 'max:255'],
            'group_patients.*.patient_email' => ['nullable', 'email', 'max:255'],
            'group_patients.*.patient_phone' => ['nullable', 'string', 'max:255'],
        ]);

        $service = Service::query()
            ->where('branch_id', $branch->id)
            ->whereKey($validated['service_id'])
            ->firstOrFail();

        $patients = $this->normalizePatients($validated, $request);

        if ($patients->isNotEmpty()) {
            throw ValidationException::withMessages([
                'patients' => 'Pacientov nie je mozne pridat pri vytvarani skupinoveho terminu. Najprv vytvorte termin, potom pridajte pacienta ku konkretnej occurrence.',
            ]);
        }

        $event = DB::transaction(function () use ($createEventAction, $branch, $validated, $service, $request): Event {
            return $createEventAction->execute($branch, [
                'type' => EventType::GroupEvent->value,
                'status' => 'confirmed',
                'starts_at' => Carbon::parse($validated['starts_at']),
                'ends_at' => Carbon::parse($validated['ends_at']),
                'timezone' => config('app.timezone'),
                'recurrence_rule' => $this->extractRecurrence($validated),
                'services' => [[
                    'service_id' => (int) $service->id,
                    'sort_order' => 0,
                    'quantity' => 1,
                ]],
                'group_detail' => [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'capacity' => (int) $validated['capacity'],
                    'reserved_places' => 0,
                    'group_status' => 'confirmed',
                    'notes' => $validated['admin_note'] ?? null,
                ],
                'metadata' => [
                    'legacy_endpoint' => 'branches.booking.capacity-windows.store',
                ],
            ], $request->user()?->id);
        });

        return back()->with('success', 'Skupinovy termin bol vytvoreny.');
    }

    public function update(
        Request $request,
        Branch $branch,
        int $capacityWindow,
        UpdateEventAction $updateEventAction,
        AddGroupEventParticipantAction $addGroupEventParticipantAction,
        RemoveGroupEventParticipantAction $removeGroupEventParticipantAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveGroupEvent($branch, $capacityWindow);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            'admin_note' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'update_scope' => ['nullable', 'in:occurrence,from_date,series,this,this_and_following,all'],
            'from_date' => ['nullable', 'date'],
            'recurrence' => ['nullable', 'array'],
            'sync_patients' => ['nullable', 'boolean'],
            'patients' => ['nullable', 'array'],
            'patients.*.patient_name' => ['required_with:patients', 'string', 'max:255'],
            'patients.*.patient_email' => ['nullable', 'email', 'max:255'],
            'patients.*.patient_phone' => ['nullable', 'string', 'max:255'],
            'group_patients' => ['nullable', 'array'],
            'group_patients.*.patient_name' => ['required_with:group_patients', 'string', 'max:255'],
            'group_patients.*.patient_email' => ['nullable', 'email', 'max:255'],
            'group_patients.*.patient_phone' => ['nullable', 'string', 'max:255'],
        ]);

        $service = Service::query()
            ->where('branch_id', $branch->id)
            ->whereKey($validated['service_id'])
            ->firstOrFail();

        $scope = $this->mapScope($validated['update_scope'] ?? (
            $event->is_recurring && $event->recurrence_parent_id === null
                ? 'series'
                : 'occurrence'
        ));

        if (array_key_exists('recurrence', $validated) && $scope === 'this') {
            // Recurrence changes must be applied to the series, not a single occurrence.
            $scope = 'series';
        }

        $occurrenceDate = filled($validated['from_date'] ?? null)
            ? Carbon::parse($validated['from_date'])
            : $event->starts_at;

        $payload = [
            'starts_at' => $validated['starts_at'] ?? $event->starts_at,
            'ends_at' => $validated['ends_at'] ?? $event->ends_at,
            'occurrence_date' => $occurrenceDate?->toDateString(),
            'services' => [[
                'service_id' => (int) $service->id,
                'sort_order' => 0,
                'quantity' => 1,
            ]],
            'group_detail' => [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'capacity' => (int) $validated['capacity'],
                'notes' => $validated['admin_note'] ?? null,
            ],
        ];

        if (array_key_exists('recurrence', $validated)) {
            $payload['recurrence_rule'] = $validated['recurrence'] ?: null;
        }

        $updatedEvent = $updateEventAction->execute($event, $payload, $request->user()?->id, $scope);

        $hasPatientPayload = $request->boolean('sync_patients') || $request->exists('patients') || $request->exists('group_patients');

        if ($hasPatientPayload) {
            if ($scope !== 'this') {
                throw ValidationException::withMessages([
                    'patients' => 'Pacientov je mozne syncovat iba pre jeden konkretny termin.',
                ]);
            }

            $patients = $this->normalizePatients($validated, $request);
            $this->syncParticipants($updatedEvent->fresh(['groupDetail', 'participants']), $patients, $addGroupEventParticipantAction, $removeGroupEventParticipantAction);
        }

        return back()->with('success', 'Skupinovy termin bol upraveny.');
    }

    public function cancel(
        Request $request,
        Branch $branch,
        int $capacityWindow,
        CancelEventAction $cancelEventAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveGroupEvent($branch, $capacityWindow);

        $cancelEventAction->execute($event, $request->user()?->id, 'series');

        return back()->with('success', 'Skupinovy termin bol zruseny.');
    }

    public function reschedule(
        Request $request,
        Branch $branch,
        int $capacityWindow,
        RescheduleEventAction $rescheduleEventAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveGroupEvent($branch, $capacityWindow);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reschedule_scope' => ['nullable', 'in:occurrence,from_date,series,this,this_and_following,all'],
            'occurrence_starts_at' => ['nullable', 'date'],
            'from_date' => ['nullable', 'date'],
            'date' => ['nullable', 'date'],
        ]);

        $occurrenceDateRaw = filled($validated['occurrence_starts_at'] ?? null)
            ? Carbon::parse($validated['occurrence_starts_at'])->toDateString()
            : ($validated['from_date'] ?? $validated['date'] ?? $event->starts_at?->toDateString());

        $scope = $this->mapScope($validated['reschedule_scope'] ?? (
            filled($validated['occurrence_starts_at'] ?? null) || filled($validated['from_date'] ?? null)
                ? 'occurrence'
                : ($event->is_recurring && $event->recurrence_parent_id === null ? 'series' : 'occurrence')
        ));

        $rescheduleEventAction->execute(
            event: $event,
            startsAt: Carbon::parse($validated['starts_at']),
            endsAt: Carbon::parse($validated['ends_at']),
            actorId: $request->user()?->id,
            scope: $scope,
            occurrenceDate: filled($occurrenceDateRaw) ? Carbon::parse($occurrenceDateRaw) : null,
        );

        return back()->with('success', 'Skupinovy termin bol presunuty.');
    }

    public function storeBooking(
        Request $request,
        Branch $branch,
        int $capacityWindow,
        AddGroupEventParticipantAction $addGroupEventParticipantAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveGroupEvent($branch, $capacityWindow);

        $validated = $request->validate([
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_birth_number' => ['nullable', 'string', 'max:255'],
            'occurrence_starts_at' => ['nullable', 'date'],
            'occurrence_ends_at' => ['nullable', 'date', 'after:occurrence_starts_at'],
            'occurrence_date' => ['nullable', 'date'],
        ]);

        if ($event->is_recurring && $event->recurrence_parent_id === null && ! filled($validated['occurrence_starts_at'] ?? null) && ! filled($validated['occurrence_date'] ?? null)) {
            throw ValidationException::withMessages([
                'occurrence_starts_at' => 'Pri opakovanom skupinovom termine je potrebne vybrat konkretnu occurrence.',
            ]);
        }

        $addGroupEventParticipantAction->execute($event, [
            'participant_name' => $validated['patient_name'],
            'participant_email' => $validated['patient_email'] ?? null,
            'participant_phone' => $validated['patient_phone'] ?? null,
            'participant_birth_number' => $validated['patient_birth_number'] ?? null,
            'occurrence_starts_at' => $validated['occurrence_starts_at'] ?? null,
            'occurrence_ends_at' => $validated['occurrence_ends_at'] ?? null,
            'occurrence_date' => $validated['occurrence_date'] ?? null,
            'status' => 'confirmed',
        ]);

        return back()->with('success', 'Pacient bol pridany do skupinoveho terminu.');
    }

    public function destroyBooking(
        Request $request,
        Branch $branch,
        int $capacityWindow,
        int $booking,
        RemoveGroupEventParticipantAction $removeGroupEventParticipantAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveGroupEvent($branch, $capacityWindow);

        $participant = $event->participants()
            ->whereKey($booking)
            ->first();

        if (! $participant) {
            throw ValidationException::withMessages([
                'booking' => 'Pacienta sa nepodarilo najst v tomto skupinovom termine.',
            ]);
        }

        $removeGroupEventParticipantAction->execute($event, $participant);

        return back()->with('success', 'Pacient bol odstraneny zo skupinoveho terminu.');
    }

    public function destroy(
        Request $request,
        Branch $branch,
        int $capacityWindow,
        DeleteEventAction $deleteEventAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveGroupEvent($branch, $capacityWindow);

        $validated = $request->validate([
            'delete_scope' => ['nullable', 'in:occurrence,from_date,series,this,this_and_following,all'],
            'from_date' => ['nullable', 'date'],
            'date' => ['nullable', 'date'],
        ]);

        $scope = $validated['delete_scope'] ?? 'occurrence';
        $normalizedScope = $this->mapScope($scope);
        $occurrenceDateRaw = $validated['from_date'] ?? $validated['date'] ?? $event->starts_at?->toDateString();

        if ($normalizedScope !== 'series') {
            request()->merge([
                'occurrence_date' => $occurrenceDateRaw,
            ]);
        }

        $deleteEventAction->execute($event, $normalizedScope);

        return back()->with('success', 'Skupinovy termin bol vymazany.');
    }

    public function destroySeries(
        Request $request,
        Branch $branch,
        int $capacityWindow,
        DeleteEventAction $deleteEventAction,
    ): RedirectResponse {
        return $this->destroy($request, $branch, $capacityWindow, $deleteEventAction);
    }

    private function resolveGroupEvent(Branch $branch, int $identifier): Event
    {
        $event = Event::query()
            ->where('branch_id', $branch->id)
            ->where('type', EventType::GroupEvent)
            ->whereKey($identifier)
            ->first();

        if ($event) {
            return $event;
        }

        $mappedEventId = DB::table('calendar_legacy_event_maps')
            ->where('branch_id', $branch->id)
            ->where('legacy_type', 'capacity_window')
            ->where('legacy_id', $identifier)
            ->value('event_id');

        if ($mappedEventId) {
            $mapped = Event::query()
                ->where('branch_id', $branch->id)
                ->where('type', EventType::GroupEvent)
                ->whereKey((int) $mappedEventId)
                ->first();

            if ($mapped) {
                return $mapped;
            }
        }

        throw ValidationException::withMessages([
            'capacity_window' => 'Skupinovy termin nebol najdeny v unified Event systeme.',
        ]);
    }

    private function extractRecurrence(array $validated): ?array
    {
        if (filled($validated['recurrence'] ?? null)) {
            return $validated['recurrence'];
        }

        if (! (bool) ($validated['repeats'] ?? false)) {
            return null;
        }

        $repeatUnit = $validated['repeat_unit'] ?? 'weeks';

        return [
            'frequency' => match ($repeatUnit) {
                'days' => 'daily',
                'months' => 'monthly',
                default => 'weekly',
            },
            'interval' => max(1, (int) ($validated['repeat_every'] ?? 1)),
            'weekdays' => $repeatUnit === 'weeks'
                ? collect($validated['repeat_weekdays'] ?? [])->values()->all()
                : [],
            'ends' => [
                'type' => filled($validated['repeat_ends_on'] ?? null) ? 'on' : 'never',
                'count' => null,
                'until' => filled($validated['repeat_ends_on'] ?? null)
                    ? Carbon::parse($validated['repeat_ends_on'])->toDateString()
                    : null,
            ],
        ];
    }

    private function normalizePatients(array $validated, Request $request)
    {
        $rawPatients = collect($validated['patients'] ?? []);

        if ($rawPatients->isEmpty() && ($request->exists('group_patients') || filled($validated['group_patients'] ?? null))) {
            $rawPatients = collect($validated['group_patients'] ?? []);
        }

        return $rawPatients
            ->filter(fn (array $patient) => filled($patient['patient_name'] ?? null))
            ->map(fn (array $patient) => [
                'patient_name' => trim((string) ($patient['patient_name'] ?? '')),
                'patient_email' => $patient['patient_email'] ?? null,
                'patient_phone' => $patient['patient_phone'] ?? null,
            ])
            ->filter(fn (array $patient) => filled($patient['patient_name']))
            ->values();
    }

    private function syncParticipants(
        Event $event,
        $patients,
        AddGroupEventParticipantAction $addGroupEventParticipantAction,
        RemoveGroupEventParticipantAction $removeGroupEventParticipantAction,
    ): void {
        $event->loadMissing(['groupDetail', 'participants']);

        $normalizedDesired = $patients
            ->map(fn (array $patient) => [
                'key' => $this->participantKey($patient['patient_name'], $patient['patient_email'] ?? null, $patient['patient_phone'] ?? null),
                'payload' => $patient,
            ])
            ->unique('key')
            ->values();

        if ($event->groupDetail && $normalizedDesired->count() > (int) $event->groupDetail->capacity) {
            throw ValidationException::withMessages([
                'patients' => 'Pocet pacientov nemoze byt vyssi ako kapacita skupinoveho terminu.',
            ]);
        }

        $existing = $event->participants
            ->where('status', 'confirmed')
            ->map(fn ($participant) => [
                'model' => $participant,
                'key' => $this->participantKey($participant->participant_name, $participant->participant_email, $participant->participant_phone),
            ])
            ->values();

        $desiredKeys = $normalizedDesired->pluck('key')->all();

        foreach ($existing as $existingParticipant) {
            if (! in_array($existingParticipant['key'], $desiredKeys, true)) {
                $removeGroupEventParticipantAction->execute($event, $existingParticipant['model']);
            }
        }

        $existingKeys = $existing->pluck('key')->all();

        foreach ($normalizedDesired as $desiredParticipant) {
            if (in_array($desiredParticipant['key'], $existingKeys, true)) {
                continue;
            }

            $payload = $desiredParticipant['payload'];

            $addGroupEventParticipantAction->execute($event, [
                'participant_name' => $payload['patient_name'],
                'participant_email' => $payload['patient_email'] ?? null,
                'participant_phone' => $payload['patient_phone'] ?? null,
                'status' => 'confirmed',
            ]);
        }
    }

    private function participantKey(?string $name, ?string $email, ?string $phone): string
    {
        return mb_strtolower(trim((string) $name))
            . '|' . mb_strtolower(trim((string) ($email ?? '')))
            . '|' . trim((string) ($phone ?? ''));
    }

    private function mapScope(string $scope): string
    {
        return match ($scope) {
            'occurrence', 'this' => 'this',
            'from_date', 'this_and_following' => 'this_and_following',
            'all', 'series' => 'series',
            default => 'series',
        };
    }

    private function authorizeAccess(Request $request, Branch $branch): void
    {
        abort_if(! $this->entitlementService->userCanManageCalendar($request->user(), $branch), 403);
    }
}