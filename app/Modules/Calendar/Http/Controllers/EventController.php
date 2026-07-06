<?php

namespace App\Modules\Calendar\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Modules\Calendar\Actions\AddGroupEventParticipantAction;
use App\Modules\Calendar\Actions\CancelEventAction;
use App\Modules\Calendar\Actions\CreateEventAction;
use App\Modules\Calendar\Actions\DeleteEventAction;
use App\Modules\Calendar\Actions\DuplicateEventAction;
use App\Modules\Calendar\Actions\RemoveGroupEventParticipantAction;
use App\Modules\Calendar\Actions\RescheduleEventAction;
use App\Modules\Calendar\Actions\ResizeEventAction;
use App\Modules\Calendar\Actions\UpdateEventAction;
use App\Modules\Calendar\Http\Requests\AddGroupEventParticipantRequest;
use App\Modules\Calendar\Http\Requests\RescheduleEventRequest;
use App\Modules\Calendar\Http\Requests\ResizeEventRequest;
use App\Modules\Calendar\Http\Requests\StoreEventRequest;
use App\Modules\Calendar\Http\Requests\UpdateEventRequest;
use App\Modules\Calendar\Http\Resources\EventResource;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Models\GroupEventParticipant;
use App\Modules\Calendar\Services\CalendarEntitlementService;
use App\Modules\Calendar\Services\EventFrontendMapper;
use App\Modules\Calendar\Services\RecurrenceExpansionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly CalendarEntitlementService $entitlementService,
    ) {
    }

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeAccess($request, $branch);

        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:booking,availability_rule,group_event'],
            'include_cancelled' => ['nullable', 'boolean'],
            'include_recurring' => ['nullable', 'boolean'],
        ]);

        $rangeStart = Carbon::parse($validated['start'])->startOfDay();
        $rangeEnd = Carbon::parse($validated['end'])->endOfDay();

        /** @var RecurrenceExpansionService $expansionService */
        $expansionService = app(RecurrenceExpansionService::class);
        $occurrences = $expansionService->forBranch(
            branch: $branch,
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
            types: $validated['types'] ?? null,
            includeCancelled: (bool) ($validated['include_cancelled'] ?? false),
        );

        $events = $occurrences->pluck('event')->unique('id')->values();

        /** @var EventFrontendMapper $mapper */
        $mapper = app(EventFrontendMapper::class);

        return response()->json([
            'data' => EventResource::collection($events),
            'occurrences' => $occurrences->map(function (array $occurrence): array {
                return [
                    'occurrence_id' => $occurrence['occurrence_id'],
                    'event_id' => $occurrence['event_id'],
                    'root_event_id' => $occurrence['root_event_id'],
                    'occurrence_starts_at' => $occurrence['occurrence_starts_at']?->toIso8601String(),
                    'occurrence_ends_at' => $occurrence['occurrence_ends_at']?->toIso8601String(),
                    'occurrence_original_starts_at' => $occurrence['occurrence_original_starts_at']?->toIso8601String(),
                    'occurrence_original_ends_at' => $occurrence['occurrence_original_ends_at']?->toIso8601String(),
                    'is_recurring' => (bool) ($occurrence['is_recurring'] ?? false),
                    'is_occurrence' => (bool) ($occurrence['is_occurrence'] ?? false),
                    'is_override' => (bool) ($occurrence['is_override'] ?? false),
                    'is_cancelled' => (bool) ($occurrence['is_cancelled'] ?? false),
                ];
            })->values(),
            'calendar' => $occurrences->map(fn (array $occurrence) => $mapper->mapExpandedOccurrenceForCalendar($occurrence))->values(),
        ]);
    }

    public function store(
        StoreEventRequest $request,
        Branch $branch,
        CreateEventAction $createEventAction,
    ): EventResource {
        $this->authorizeAccess($request, $branch);

        $event = $createEventAction->execute(
            branch: $branch,
            payload: $request->validated(),
            actorId: $request->user()?->id,
        );

        return new EventResource($event);
    }

    public function show(Request $request, Branch $branch, Event $event): EventResource
    {
        $this->authorizeAccess($request, $branch);
        $this->ensureBelongsToBranch($branch, $event);

        $event->load(['services', 'bookingDetail', 'availabilityRuleDetail', 'groupDetail', 'participants']);

        return new EventResource($event);
    }

    public function update(
        UpdateEventRequest $request,
        Branch $branch,
        Event $event,
        UpdateEventAction $updateEventAction,
    ): EventResource {
        $this->authorizeAccess($request, $branch);
        $this->ensureBelongsToBranch($branch, $event);

        $event = $updateEventAction->execute(
            event: $event,
            payload: $request->validated(),
            actorId: $request->user()?->id,
            scope: $request->input('recurrence_scope'),
        );

        return new EventResource($event);
    }

    public function reschedule(
        RescheduleEventRequest $request,
        Branch $branch,
        Event $event,
        RescheduleEventAction $rescheduleEventAction,
    ): EventResource {
        $this->authorizeAccess($request, $branch);
        $this->ensureBelongsToBranch($branch, $event);

        $event = $rescheduleEventAction->execute(
            event: $event,
            startsAt: Carbon::parse($request->input('starts_at')),
            endsAt: Carbon::parse($request->input('ends_at')),
            actorId: $request->user()?->id,
            scope: $request->input('recurrence_scope'),
            occurrenceDate: $request->filled('occurrence_date')
                ? Carbon::parse((string) $request->input('occurrence_date'))
                : null,
        );

        return new EventResource($event);
    }

    public function resize(
        ResizeEventRequest $request,
        Branch $branch,
        Event $event,
        ResizeEventAction $resizeEventAction,
    ): EventResource {
        $this->authorizeAccess($request, $branch);
        $this->ensureBelongsToBranch($branch, $event);

        $event = $resizeEventAction->execute(
            event: $event,
            endsAt: Carbon::parse($request->input('ends_at')),
            actorId: $request->user()?->id,
            scope: $request->input('recurrence_scope'),
        );

        return new EventResource($event);
    }

    public function destroy(
        Request $request,
        Branch $branch,
        Event $event,
        DeleteEventAction $deleteEventAction,
    ): JsonResponse {
        $this->authorizeAccess($request, $branch);
        $this->ensureBelongsToBranch($branch, $event);

        $deleteEventAction->execute($event, $request->input('recurrence_scope'));

        return response()->json([
            'message' => 'Event bol zmazany.',
        ]);
    }

    public function cancel(
        Request $request,
        Branch $branch,
        Event $event,
        CancelEventAction $cancelEventAction,
    ): EventResource {
        $this->authorizeAccess($request, $branch);
        $this->ensureBelongsToBranch($branch, $event);

        $event = $cancelEventAction->execute(
            event: $event,
            actorId: $request->user()?->id,
            scope: $request->input('recurrence_scope'),
        );

        return new EventResource($event);
    }

    public function duplicate(
        Request $request,
        Branch $branch,
        Event $event,
        DuplicateEventAction $duplicateEventAction,
    ): EventResource {
        $this->authorizeAccess($request, $branch);
        $this->ensureBelongsToBranch($branch, $event);

        $event = $duplicateEventAction->execute($event, $request->user()?->id);

        return new EventResource($event);
    }

    public function addParticipant(
        AddGroupEventParticipantRequest $request,
        Branch $branch,
        Event $event,
        AddGroupEventParticipantAction $addGroupEventParticipantAction,
    ): JsonResponse {
        $this->authorizeAccess($request, $branch);
        $this->ensureBelongsToBranch($branch, $event);

        $participant = $addGroupEventParticipantAction->execute($event, $request->validated());

        return response()->json([
            'participant' => $participant,
            'event' => new EventResource($event->fresh(['groupDetail', 'participants'])),
        ]);
    }

    public function removeParticipant(
        Request $request,
        Branch $branch,
        Event $event,
        GroupEventParticipant $participant,
        RemoveGroupEventParticipantAction $removeGroupEventParticipantAction,
    ): JsonResponse {
        $this->authorizeAccess($request, $branch);
        $this->ensureBelongsToBranch($branch, $event);

        $removeGroupEventParticipantAction->execute($event, $participant);

        return response()->json([
            'event' => new EventResource($event->fresh(['groupDetail', 'participants'])),
        ]);
    }

    private function authorizeAccess(Request $request, Branch $branch): void
    {
        abort_if(! $this->entitlementService->userCanManageCalendar($request->user(), $branch), 403);
    }

    private function ensureBelongsToBranch(Branch $branch, Event $event): void
    {
        abort_if((int) $event->branch_id !== (int) $branch->id, 404);
    }
}
