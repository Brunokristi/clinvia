<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Modules\Calendar\Actions\CancelEventAction;
use App\Modules\Calendar\Actions\CreateEventAction;
use App\Modules\Calendar\Actions\DeleteEventAction;
use App\Modules\Calendar\Actions\DuplicateEventAction;
use App\Modules\Calendar\Actions\RescheduleEventAction;
use App\Modules\Calendar\Actions\UpdateEventAction;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\CalendarEntitlementService;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchBookingEventBridgeController extends Controller
{
    public function __construct(
        private readonly CalendarEntitlementService $entitlementService,
    ) {
    }

    public function store(
        Request $request,
        Branch $branch,
        CreateEventAction $createEventAction,
        RecurrenceService $recurrenceService,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_birth_number' => ['nullable', 'string', 'max:255'],
            'patient_note' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.frequency' => ['required_with:recurrence', 'in:daily,weekly,monthly,yearly'],
            'recurrence.interval' => ['nullable', 'integer', 'min:1'],
            'recurrence.weekdays' => ['nullable', 'array'],
            'recurrence.weekdays.*' => ['in:MO,TU,WE,TH,FR,SA,SU'],
            'recurrence.ends' => ['nullable', 'array'],
            'recurrence.ends.type' => ['required_with:recurrence.ends', 'in:never,on,after'],
            'recurrence.ends.count' => ['nullable', 'integer', 'min:1'],
            'recurrence.ends.until' => ['nullable', 'date'],
        ]);

        $serviceIds = collect($validated['service_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->push((int) $validated['service_id'])
            ->unique()
            ->values();

        $normalizedRecurrence = filled($validated['recurrence'] ?? null)
            ? $recurrenceService->normalize($validated['recurrence'])
            : null;

        $createEventAction->execute($branch, [
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? Carbon::parse($validated['starts_at'])->addMinutes(30),
            'recurrence_rule' => $normalizedRecurrence,
            'title' => null,
            'services' => $serviceIds
                ->map(fn (int $serviceId, int $index) => [
                    'service_id' => $serviceId,
                    'sort_order' => $index,
                    'quantity' => 1,
                ])
                ->values()
                ->all(),
            'booking_detail' => [
                'booking_source' => 'admin_calendar',
                'booking_status' => 'confirmed',
                'internal_notes' => $validated['admin_note'] ?? null,
                'public_notes' => $validated['patient_note'] ?? null,
                'patient_name' => $validated['patient_name'],
                'patient_email' => $validated['patient_email'] ?? null,
                'patient_phone' => $validated['patient_phone'] ?? null,
                'patient_birth_number' => $validated['patient_birth_number'] ?? null,
            ],
            'metadata' => [
                'legacy_endpoint' => 'branches.booking.bookings.store',
            ],
        ], $request->user()?->id);

        return back()->with('success', 'Rezervacia bola vytvorena.');
    }

    public function update(
        Request $request,
        Branch $branch,
        int $booking,
        UpdateEventAction $updateEventAction,
        CancelEventAction $cancelEventAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveBookingEvent($branch, $booking);

        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,cancelled,completed,no_show'],
            'admin_note' => ['nullable', 'string'],
        ]);

        if ($validated['status'] === 'cancelled') {
            $cancelEventAction->execute($event, $request->user()?->id, 'series');

            return back()->with('success', 'Rezervacia bola zrusena.');
        }

        $updateEventAction->execute($event, [
            'status' => $validated['status'],
            'booking_detail' => [
                'internal_notes' => $validated['admin_note'] ?? $event->bookingDetail?->internal_notes,
                'booking_status' => $validated['status'],
            ],
        ], $request->user()?->id, 'series');

        return back()->with('success', 'Rezervacia bola upravena.');
    }

    public function cancel(
        Request $request,
        Branch $branch,
        int $booking,
        CancelEventAction $cancelEventAction,
        DeleteEventAction $deleteEventAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveBookingEvent($branch, $booking);

        $validated = $request->validate([
            'delete_scope' => ['nullable', 'in:occurrence,from_date,series,this,this_and_following,all'],
            'date' => ['nullable', 'date'],
        ]);

        $scope = $this->normalizeScope($validated['delete_scope'] ?? 'series');

        if ($scope === 'this') {
            request()->merge(['occurrence_date' => $validated['date'] ?? $event->starts_at?->toDateString()]);
            $deleteEventAction->execute($event, 'this');

            return back()->with('success', 'Vyskyt rezervacie bol zruseny.');
        }

        if ($scope === 'this_and_following') {
            request()->merge(['occurrence_date' => $validated['date'] ?? $event->starts_at?->toDateString()]);
            $deleteEventAction->execute($event, 'this_and_following');

            return back()->with('success', 'Tento a nasledujuce vyskyty boli zrusene.');
        }

        $cancelEventAction->execute($event, $request->user()?->id, 'series');

        return back()->with('success', 'Rezervacia bola zrusena.');
    }

    public function reschedule(
        Request $request,
        Branch $branch,
        int $booking,
        RescheduleEventAction $rescheduleEventAction,
        UpdateEventAction $updateEventAction,
        RecurrenceService $recurrenceService,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveBookingEvent($branch, $booking);

        $validated = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_birth_number' => ['nullable', 'string', 'max:255'],
            'patient_note' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
            'reschedule_scope' => ['nullable', 'in:occurrence,from_date,series,this,this_and_following,all'],
            'date' => ['nullable', 'date'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.frequency' => ['required_with:recurrence', 'in:daily,weekly,monthly,yearly'],
            'recurrence.interval' => ['nullable', 'integer', 'min:1'],
            'recurrence.weekdays' => ['nullable', 'array'],
            'recurrence.weekdays.*' => ['in:MO,TU,WE,TH,FR,SA,SU'],
            'recurrence.ends' => ['nullable', 'array'],
            'recurrence.ends.type' => ['required_with:recurrence.ends', 'in:never,on,after'],
            'recurrence.ends.count' => ['nullable', 'integer', 'min:1'],
            'recurrence.ends.until' => ['nullable', 'date'],
        ]);

        $scope = $this->normalizeScope($validated['reschedule_scope'] ?? 'series');

        $rescheduledEvent = $rescheduleEventAction->execute(
            event: $event,
            startsAt: Carbon::parse($validated['starts_at']),
            endsAt: Carbon::parse($validated['ends_at'] ?? $event->ends_at),
            actorId: $request->user()?->id,
            scope: $scope,
            occurrenceDate: filled($validated['date'] ?? null)
                ? Carbon::parse($validated['date'])
                : null,
        );

        $serviceIds = collect($validated['service_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($serviceIds->isEmpty() && filled($validated['service_id'] ?? null)) {
            $serviceIds->push((int) $validated['service_id']);
        }

        if ($serviceIds->isEmpty()) {
            $serviceIds = $rescheduledEvent->services
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();
        }

        $updatePayload = [
            'booking_detail' => [
                'patient_name' => $validated['patient_name'] ?? $rescheduledEvent->bookingDetail?->patient_name,
                'patient_email' => $validated['patient_email'] ?? $rescheduledEvent->bookingDetail?->patient_email,
                'patient_phone' => $validated['patient_phone'] ?? $rescheduledEvent->bookingDetail?->patient_phone,
                'patient_birth_number' => $validated['patient_birth_number'] ?? $rescheduledEvent->bookingDetail?->patient_birth_number,
                'public_notes' => $validated['patient_note'] ?? $rescheduledEvent->bookingDetail?->public_notes,
                'internal_notes' => $validated['admin_note'] ?? $rescheduledEvent->bookingDetail?->internal_notes,
                'booking_status' => $rescheduledEvent->status,
            ],
        ];

        if ($serviceIds->isNotEmpty()) {
            $updatePayload['services'] = $serviceIds
                ->values()
                ->map(fn (int $serviceId, int $index) => [
                    'service_id' => $serviceId,
                    'sort_order' => $index,
                    'quantity' => 1,
                ])
                ->all();
        }

        $updateEventAction->execute(
            event: $rescheduledEvent->fresh(['services', 'bookingDetail']),
            payload: $updatePayload,
            actorId: $request->user()?->id,
            scope: 'series',
        );

        if ($request->exists('recurrence')) {
            $normalizedRecurrence = filled($validated['recurrence'] ?? null)
                ? $recurrenceService->normalize($validated['recurrence'])
                : null;

            $currentRecurrence = filled($rescheduledEvent->recurrence_rule)
                ? $recurrenceService->normalize($rescheduledEvent->recurrence_rule)
                : null;

            if ($normalizedRecurrence == $currentRecurrence) {
                return back()->with('success', 'Rezervacia bola presunuta.');
            }

            $recurrenceScope = $scope;

            if ($normalizedRecurrence === null && $scope === 'series' && filled($validated['date'] ?? null)) {
                $recurrenceScope = 'this_and_following';
            }

            $updateEventAction->execute($rescheduledEvent->fresh(), [
                'recurrence_rule' => $normalizedRecurrence,
                'occurrence_date' => $validated['date'] ?? null,
            ], $request->user()?->id, $recurrenceScope);
        }

        return back()->with('success', 'Rezervacia bola presunuta.');
    }

    public function duplicate(
        Request $request,
        Branch $branch,
        int $booking,
        DuplicateEventAction $duplicateEventAction,
        UpdateEventAction $updateEventAction,
        RecurrenceService $recurrenceService,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveBookingEvent($branch, $booking);

        $validated = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_birth_number' => ['nullable', 'string', 'max:255'],
            'patient_note' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
            'recurrence' => ['nullable', 'array'],
        ]);

        $duplicate = $duplicateEventAction->execute($event, $request->user()?->id);

        $serviceIds = collect($validated['service_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter();

        if ($serviceIds->isEmpty() && filled($validated['service_id'] ?? null)) {
            $serviceIds->push((int) $validated['service_id']);
        }

        $payload = [
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? Carbon::parse($validated['starts_at'])->addMinutes(30),
            'booking_detail' => [
                'patient_name' => $validated['patient_name'],
                'patient_email' => $validated['patient_email'] ?? null,
                'patient_phone' => $validated['patient_phone'] ?? null,
                'patient_birth_number' => $validated['patient_birth_number'] ?? null,
                'public_notes' => $validated['patient_note'] ?? null,
                'internal_notes' => $validated['admin_note'] ?? null,
            ],
        ];

        if ($serviceIds->isNotEmpty()) {
            $payload['services'] = $serviceIds
                ->values()
                ->map(fn (int $serviceId, int $index) => [
                    'service_id' => $serviceId,
                    'sort_order' => $index,
                    'quantity' => 1,
                ])
                ->all();
        }

        if (filled($validated['recurrence'] ?? null)) {
            $payload['recurrence_rule'] = $recurrenceService->normalize($validated['recurrence']);
        }

        $updateEventAction->execute($duplicate, $payload, $request->user()?->id, 'series');

        return back()->with('success', 'Rezervacia bola duplikovana.');
    }

    private function resolveBookingEvent(Branch $branch, int $identifier): Event
    {
        $event = Event::query()
            ->where('branch_id', $branch->id)
            ->where('type', EventType::Booking)
            ->whereKey($identifier)
            ->first();

        if ($event) {
            return $event;
        }

        $mappedEventId = DB::table('calendar_legacy_event_maps')
            ->where('branch_id', $branch->id)
            ->where('legacy_type', 'booking')
            ->where('legacy_id', $identifier)
            ->value('event_id');

        if ($mappedEventId) {
            $mappedEvent = Event::query()
                ->where('branch_id', $branch->id)
                ->where('type', EventType::Booking)
                ->whereKey((int) $mappedEventId)
                ->first();

            if ($mappedEvent) {
                return $mappedEvent;
            }
        }

        throw ValidationException::withMessages([
            'booking' => 'Rezervacia nebola najdena v unified Event systeme.',
        ]);
    }

    private function authorizeAccess(Request $request, Branch $branch): void
    {
        abort_if(! $this->entitlementService->userCanManageCalendar($request->user(), $branch), 403);
    }

    private function normalizeScope(string $scope): string
    {
        return match ($scope) {
            'occurrence', 'this' => 'this',
            'from_date', 'this_and_following' => 'this_and_following',
            'all', 'series' => 'series',
            default => 'series',
        };
    }
}
