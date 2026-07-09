<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Modules\Calendar\Actions\CreateEventAction;
use App\Modules\Calendar\Actions\DeleteEventAction;
use App\Modules\Calendar\Actions\RescheduleEventAction;
use App\Modules\Calendar\Actions\UpdateEventAction;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\CalendarEntitlementService;
use App\Services\DisabledDayService;
use App\Services\OpeningHoursService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchAvailabilityRuleEventBridgeController extends Controller
{
    public function __construct(
        private readonly CalendarEntitlementService $entitlementService,
        private readonly DisabledDayService $disabledDayService,
        private readonly OpeningHoursService $openingHoursService,
    ) {
    }

    public function sync(
        Request $request,
        Branch $branch,
        CreateEventAction $createEventAction,
        UpdateEventAction $updateEventAction,
        DeleteEventAction $deleteEventAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);
        $actorId = $request->user()?->id;

        $validated = $request->validate([
            'rules' => ['required', 'array'],
            'staff_id' => ['prohibited'],
            'rules.*.id' => ['nullable', 'integer'],
            'rules.*.date' => ['required', 'date'],
            'rules.*.starts_at' => ['required', 'date_format:H:i'],
            'rules.*.ends_at' => ['required', 'date_format:H:i'],
            'rules.*.service_ids' => ['required', 'array', 'min:1'],
            'rules.*.service_ids.*' => ['integer', 'exists:services,id'],
            'rules.*.repeats' => ['required', 'boolean'],
            'rules.*.repeat_every' => ['nullable', 'integer', 'min:1'],
            'rules.*.repeat_unit' => ['nullable', 'in:days,weeks,months'],
            'rules.*.repeat_weekdays' => ['nullable', 'array'],
            'rules.*.repeat_weekdays.*' => ['in:MO,TU,WE,TH,FR,SA,SU'],
            'rules.*.repeat_ends_on' => ['nullable', 'date'],
            'rules.*.recurrence' => ['nullable', 'array'],
            'rules.*.recurrence.frequency' => ['required_with:rules.*.recurrence', 'in:daily,weekly,monthly,yearly'],
            'rules.*.recurrence.interval' => ['nullable', 'integer', 'min:1'],
            'rules.*.recurrence.weekdays' => ['nullable', 'array'],
            'rules.*.recurrence.weekdays.*' => ['in:MO,TU,WE,TH,FR,SA,SU'],
            'rules.*.recurrence.ends' => ['nullable', 'array'],
            'rules.*.recurrence.ends.type' => ['required_with:rules.*.recurrence.ends', 'in:never,on,after'],
            'rules.*.recurrence.ends.count' => ['nullable', 'integer', 'min:1'],
            'rules.*.recurrence.ends.until' => ['nullable', 'date'],
            'rules.*.excluded_dates' => ['nullable', 'array'],
            'rules.*.excluded_dates.*' => ['date'],
            'rules.*.is_enabled' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($validated, $branch, $createEventAction, $updateEventAction, $deleteEventAction, $actorId): void {
            $keepEventIds = [];

            foreach ($validated['rules'] as $index => $ruleData) {
                $date = Carbon::parse($ruleData['date'])->toDateString();
                $startsAt = Carbon::parse($date . ' ' . $ruleData['starts_at']);
                $endsAt = Carbon::parse($date . ' ' . $ruleData['ends_at']);

                if ($this->disabledDayService->isDisabled($branch, $startsAt)) {
                    throw ValidationException::withMessages([
                        "rules.{$index}.date" => 'Tento deň je v kalendári zakázaný.',
                    ]);
                }

                if (! $this->openingHoursService->isWithinOpeningHours($branch, $startsAt, $endsAt)) {
                    throw ValidationException::withMessages([
                        "rules.{$index}.starts_at" => 'Termín musí byť v rámci otváracích hodín.',
                    ]);
                }

                if ($endsAt->lessThanOrEqualTo($startsAt)) {
                    throw ValidationException::withMessages([
                        "rules.{$index}.ends_at" => 'Koniec musi byt neskor ako zaciatok.',
                    ]);
                }

                $serviceIds = collect($ruleData['service_ids'])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();

                $payload = [
                    'status' => (bool) $ruleData['is_enabled'] ? 'confirmed' : 'cancelled',
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'timezone' => config('app.timezone'),
                    'recurrence_rule' => $this->buildRecurrenceRule($ruleData),
                    'services' => $serviceIds
                        ->map(fn (int $serviceId, int $order) => [
                            'service_id' => $serviceId,
                            'sort_order' => $order,
                            'quantity' => 1,
                        ])
                        ->values()
                        ->all(),
                    'availability_rule_detail' => [
                        'slot_interval_minutes' => 15,
                    ],
                    'metadata' => [
                        'legacy_endpoint' => 'branches.booking.rules.update',
                        'recurrence_excluded_dates' => collect($ruleData['excluded_dates'] ?? [])
                            ->map(fn ($excludedDate) => Carbon::parse($excludedDate)->toDateString())
                            ->unique()
                            ->values()
                            ->all(),
                    ],
                ];

                $existingEvent = null;

                if (filled($ruleData['id'] ?? null)) {
                    $existingEvent = $this->resolveRuleEvent($branch, (int) $ruleData['id']);
                }

                if ($existingEvent) {
                    $event = $updateEventAction->execute($existingEvent, $payload, $actorId, 'series');
                } else {
                    $event = $createEventAction->execute($branch, [
                        ...$payload,
                        'type' => EventType::AvailabilityRule->value,
                    ], $actorId);
                }

                $keepEventIds[] = $event->id;
            }

            $query = Event::query()
                ->where('branch_id', $branch->id)
                ->where('type', EventType::AvailabilityRule);

            if ($keepEventIds !== []) {
                $query->whereNotIn('id', $keepEventIds);
            }

            $query->get()->each(fn (Event $event) => $deleteEventAction->execute($event, 'series'));
        });

        return back()->with('success', 'Pravidla dostupnosti boli ulozene.');
    }

    public function reschedule(
        Request $request,
        Branch $branch,
        int $rule,
        RescheduleEventAction $rescheduleEventAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveRuleEventOrFail($branch, $rule);

        $validated = $request->validate([
            'occurrence_date' => ['required', 'date'],
            'date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'staff_id' => ['prohibited'],
            'reschedule_scope' => ['required', 'in:occurrence,series,from_date,this,this_and_following,all'],
        ]);

        $targetDate = Carbon::parse($validated['date'])->toDateString();
        $startsAt = Carbon::parse($targetDate . ' ' . $validated['starts_at']);
        $endsAt = Carbon::parse($targetDate . ' ' . $validated['ends_at']);

        $rescheduleEventAction->execute(
            event: $event,
            startsAt: $startsAt,
            endsAt: $endsAt,
            actorId: $request->user()?->id,
            scope: $this->mapScope($validated['reschedule_scope']),
            occurrenceDate: Carbon::parse($validated['occurrence_date']),
        );

        return back()->with('success', 'Pravidlo dostupnosti bolo presunute.');
    }

    public function deleteSeries(
        Request $request,
        Branch $branch,
        int $rule,
        DeleteEventAction $deleteEventAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveRuleEventOrFail($branch, $rule);

        $deleteEventAction->execute($event, 'series');

        return back()->with('success', 'Pravidlo bolo vymazane.');
    }

    public function deleteOccurrence(
        Request $request,
        Branch $branch,
        int $rule,
        DeleteEventAction $deleteEventAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveRuleEventOrFail($branch, $rule);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        request()->merge([
            'occurrence_date' => Carbon::parse($validated['date'])->toDateString(),
        ]);

        $deleteEventAction->execute($event, 'this');

        return back()->with('success', 'Tento vyskyt bol vymazany.');
    }

    public function deleteFutureOccurrences(
        Request $request,
        Branch $branch,
        int $rule,
        DeleteEventAction $deleteEventAction,
    ): RedirectResponse {
        $this->authorizeAccess($request, $branch);

        $event = $this->resolveRuleEventOrFail($branch, $rule);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        request()->merge([
            'occurrence_date' => Carbon::parse($validated['date'])->toDateString(),
        ]);

        $deleteEventAction->execute($event, 'this_and_following');

        return back()->with('success', 'Tento a nasledujuce vyskyty boli vymazane.');
    }

    private function resolveRuleEvent(Branch $branch, int $identifier): ?Event
    {
        $event = Event::query()
            ->where('branch_id', $branch->id)
            ->where('type', EventType::AvailabilityRule)
            ->whereKey($identifier)
            ->first();

        if ($event) {
            return $event;
        }

        $mappedEventId = DB::table('calendar_legacy_event_maps')
            ->where('branch_id', $branch->id)
            ->where('legacy_type', 'availability_rule')
            ->where('legacy_id', $identifier)
            ->value('event_id');

        if (! $mappedEventId) {
            return null;
        }

        return Event::query()
            ->where('branch_id', $branch->id)
            ->where('type', EventType::AvailabilityRule)
            ->whereKey((int) $mappedEventId)
            ->first();
    }

    private function resolveRuleEventOrFail(Branch $branch, int $identifier): Event
    {
        $event = $this->resolveRuleEvent($branch, $identifier);

        if ($event) {
            return $event;
        }

        throw ValidationException::withMessages([
            'rule' => 'Pravidlo dostupnosti nebolo najdene v unified Event systeme.',
        ]);
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

    private function buildRecurrenceRule(array $ruleData): ?array
    {
        if (filled($ruleData['recurrence'] ?? null)) {
            return $ruleData['recurrence'];
        }

        if (! (bool) ($ruleData['repeats'] ?? false)) {
            return null;
        }

        $repeatUnit = $ruleData['repeat_unit'] ?? 'weeks';

        return [
            'frequency' => match ($repeatUnit) {
                'days' => 'daily',
                'months' => 'monthly',
                default => 'weekly',
            },
            'interval' => max(1, (int) ($ruleData['repeat_every'] ?? 1)),
            'weekdays' => $repeatUnit === 'weeks'
                ? collect($ruleData['repeat_weekdays'] ?? [])->values()->all()
                : [],
            'ends' => [
                'type' => filled($ruleData['repeat_ends_on'] ?? null) ? 'on' : 'never',
                'count' => null,
                'until' => filled($ruleData['repeat_ends_on'] ?? null)
                    ? Carbon::parse($ruleData['repeat_ends_on'])->toDateString()
                    : null,
            ],
        ];
    }

    private function authorizeAccess(Request $request, Branch $branch): void
    {
        abort_if(! $this->entitlementService->userCanManageCalendar($request->user(), $branch), 403);
    }
}