<?php

namespace App\Http\Controllers\Admin;

use App\Events\BranchCalendarUpdated;
use App\Http\Controllers\Controller;
use App\Models\BookingAvailabilityRule;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BranchAvailabilityRuleController extends Controller
{
    public function sync(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $validated = $request->validate([
            'rules' => ['required', 'array'],

            'rules.*.id' => [
                'nullable',
                'integer',
            ],

            'rules.*.date' => ['required', 'date'],
            'rules.*.starts_at' => ['required', 'date_format:H:i'],
            'rules.*.ends_at' => ['required', 'date_format:H:i', 'after:rules.*.starts_at'],

            'rules.*.service_ids' => ['required', 'array', 'min:1'],
            'rules.*.service_ids.*' => ['integer', 'exists:services,id'],

            'rules.*.repeats' => ['required', 'boolean'],
            'rules.*.repeat_every' => ['nullable', 'integer', 'min:1'],
            'rules.*.repeat_unit' => ['nullable', 'in:days,weeks,months'],
            'rules.*.repeat_ends_on' => ['nullable', 'date'],

            'rules.*.excluded_dates' => ['nullable', 'array'],
            'rules.*.excluded_dates.*' => ['date'],

            'rules.*.is_enabled' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($branch, $validated): void {
            $keepIds = [];

            foreach ($validated['rules'] as $ruleData) {
                $date = Carbon::parse($ruleData['date'])->startOfDay();
                $serviceIds = $this->normalizeServiceIds($ruleData['service_ids'] ?? []);

                $rule = null;

                if (! empty($ruleData['id'])) {
                    $rule = BookingAvailabilityRule::query()
                        ->where('branch_id', $branch->id)
                        ->whereKey($ruleData['id'])
                        ->first();
                }

                $rule ??= new BookingAvailabilityRule([
                    'branch_id' => $branch->id,
                ]);

                $repeats = (bool) $ruleData['repeats'];

                $rule->fill([
                    'branch_id' => $branch->id,

                    'date' => $date->toDateString(),
                    'day_of_week' => $date->dayOfWeekIso,

                    'starts_at' => $ruleData['starts_at'],
                    'ends_at' => $ruleData['ends_at'],

                    'slot_mode' => 'free_bookable_time',
                    'service_id' => null,
                    'service_ids' => $serviceIds,
                    'bookable_places' => 1,

                    'repeats' => $repeats,
                    'repeat_every' => $repeats ? (int) ($ruleData['repeat_every'] ?? 1) : 1,
                    'repeat_unit' => $repeats ? ($ruleData['repeat_unit'] ?? 'weeks') : 'weeks',
                    'repeat_ends_on' => $repeats ? ($ruleData['repeat_ends_on'] ?? null) : null,

                    'excluded_dates' => $this->normalizeDateStrings($ruleData['excluded_dates'] ?? []),
                    'is_enabled' => (bool) $ruleData['is_enabled'],
                ]);

                $rule->save();
                $rule->services()->sync($serviceIds);

                $keepIds[] = $rule->id;
            }

            BookingAvailabilityRule::query()
                ->where('branch_id', $branch->id)
                ->when($keepIds !== [], function ($query) use ($keepIds) {
                    $query->whereNotIn('id', $keepIds);
                })
                ->get()
                ->each(function (BookingAvailabilityRule $rule): void {
                    $this->deleteRuleCompletely($rule);
                });
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'availability_rules_updated',
        );

        return back()->with('success', 'Pravidlá dostupnosti boli uložené.');
    }

    public function reschedule(
        Request $request,
        Branch $branch,
        BookingAvailabilityRule $rule,
    ): RedirectResponse {
        $this->authorizeRule($request, $branch, $rule);

        $validated = $request->validate([
            'occurrence_date' => ['required', 'date'],
            'date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],

            /**
             * occurrence = only clicked instance
             * series = whole recurring rule
             * from_date = clicked instance and following instances
             */
            'reschedule_scope' => ['required', 'in:occurrence,series,from_date'],
        ]);

        $occurrenceDate = Carbon::parse($validated['occurrence_date'])->startOfDay();
        $targetDate = Carbon::parse($validated['date'])->startOfDay();

        DB::transaction(function () use ($rule, $validated, $occurrenceDate, $targetDate): void {
            $scope = $validated['reschedule_scope'];

            if (! $rule->repeats) {
                $this->moveRuleSeries(
                    rule: $rule,
                    targetDate: $targetDate,
                    startsAt: $validated['starts_at'],
                    endsAt: $validated['ends_at'],
                );

                return;
            }

            if ($scope === 'series') {
                $this->moveRuleSeries(
                    rule: $rule,
                    targetDate: $targetDate,
                    startsAt: $validated['starts_at'],
                    endsAt: $validated['ends_at'],
                );

                return;
            }

            if ($scope === 'occurrence') {
                $this->excludeOccurrence($rule, $occurrenceDate);

                $this->createRuleCopy(
                    sourceRule: $rule,
                    targetDate: $targetDate,
                    startsAt: $validated['starts_at'],
                    endsAt: $validated['ends_at'],
                    repeats: false,
                    repeatEndsOn: null,
                    excludedDates: [],
                );

                return;
            }

            if ($scope === 'from_date') {
                $this->rescheduleThisAndFollowing(
                    rule: $rule,
                    occurrenceDate: $occurrenceDate,
                    targetDate: $targetDate,
                    startsAt: $validated['starts_at'],
                    endsAt: $validated['ends_at'],
                );
            }
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'availability_rule_rescheduled',
        );

        return back()->with('success', 'Pravidlo dostupnosti bolo presunuté.');
    }

    public function deleteSeries(
        Request $request,
        Branch $branch,
        BookingAvailabilityRule $rule,
    ): RedirectResponse {
        $this->authorizeRule($request, $branch, $rule);

        DB::transaction(function () use ($rule): void {
            $this->deleteRuleCompletely($rule);
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'availability_rule_deleted',
        );

        return back()->with('success', 'Pravidlo bolo vymazané.');
    }

    public function deleteOccurrence(
        Request $request,
        Branch $branch,
        BookingAvailabilityRule $rule,
    ): RedirectResponse {
        $this->authorizeRule($request, $branch, $rule);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $occurrenceDate = Carbon::parse($validated['date'])->startOfDay();

        DB::transaction(function () use ($rule, $occurrenceDate): void {
            if (! $rule->repeats) {
                $this->deleteRuleCompletely($rule);

                return;
            }

            $this->excludeOccurrence($rule, $occurrenceDate);
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'availability_rule_occurrence_deleted',
        );

        return back()->with('success', 'Tento výskyt bol vymazaný.');
    }

    public function deleteFutureOccurrences(
        Request $request,
        Branch $branch,
        BookingAvailabilityRule $rule,
    ): RedirectResponse {
        $this->authorizeRule($request, $branch, $rule);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $occurrenceDate = Carbon::parse($validated['date'])->startOfDay();

        DB::transaction(function () use ($rule, $occurrenceDate): void {
            if (! $rule->repeats) {
                $this->deleteRuleCompletely($rule);

                return;
            }

            $ruleStartDate = Carbon::parse($rule->date)->startOfDay();

            if ($occurrenceDate->lessThanOrEqualTo($ruleStartDate)) {
                $this->deleteRuleCompletely($rule);

                return;
            }

            $rule->update([
                'repeat_ends_on' => $occurrenceDate->copy()->subDay()->toDateString(),
                'excluded_dates' => collect($rule->excluded_dates ?? [])
                    ->map(fn ($date): string => Carbon::parse($date)->startOfDay()->toDateString())
                    ->filter(fn (string $date): bool => $date < $occurrenceDate->toDateString())
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
            ]);
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'availability_rule_future_deleted',
        );

        return back()->with('success', 'Tento a nasledujúce výskyty boli vymazané.');
    }

    private function rescheduleThisAndFollowing(
        BookingAvailabilityRule $rule,
        Carbon $occurrenceDate,
        Carbon $targetDate,
        string $startsAt,
        string $endsAt,
    ): void {
        $ruleStartDate = Carbon::parse($rule->date)->startOfDay();

        if ($occurrenceDate->lessThanOrEqualTo($ruleStartDate)) {
            $this->moveRuleSeries(
                rule: $rule,
                targetDate: $targetDate,
                startsAt: $startsAt,
                endsAt: $endsAt,
            );

            return;
        }

        $originalRepeatEndsOn = $rule->repeat_ends_on;

        $oldExcludedDates = collect($rule->excluded_dates ?? [])
            ->map(fn ($date): string => Carbon::parse($date)->startOfDay()->toDateString())
            ->unique()
            ->sort()
            ->values();

        $rule->update([
            'repeat_ends_on' => $occurrenceDate->copy()->subDay()->toDateString(),
            'excluded_dates' => $oldExcludedDates
                ->filter(fn (string $date): bool => $date < $occurrenceDate->toDateString())
                ->values()
                ->all(),
        ]);

        $futureExcludedDates = $oldExcludedDates
            ->filter(fn (string $date): bool => $date >= $occurrenceDate->toDateString())
            ->values()
            ->all();

        $this->createRuleCopy(
            sourceRule: $rule,
            targetDate: $targetDate,
            startsAt: $startsAt,
            endsAt: $endsAt,
            repeats: true,
            repeatEndsOn: $originalRepeatEndsOn,
            excludedDates: $futureExcludedDates,
        );
    }

    private function authorizeRule(Request $request, Branch $branch, BookingAvailabilityRule $rule): void
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);
    }

    private function deleteRuleCompletely(BookingAvailabilityRule $rule): void
    {
        $rule->services()->detach();
        $rule->delete();
    }

    private function excludeOccurrence(BookingAvailabilityRule $rule, Carbon $occurrenceDate): void
    {
        $dateString = $occurrenceDate->toDateString();

        $excludedDates = collect($rule->excluded_dates ?? [])
            ->map(fn ($date): string => Carbon::parse($date)->startOfDay()->toDateString())
            ->push($dateString)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $rule->update([
            'excluded_dates' => $excludedDates,
        ]);
    }

    private function moveRuleSeries(
        BookingAvailabilityRule $rule,
        Carbon $targetDate,
        string $startsAt,
        string $endsAt,
    ): void {
        $rule->update([
            'date' => $targetDate->toDateString(),
            'day_of_week' => $targetDate->dayOfWeekIso,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    private function createRuleCopy(
        BookingAvailabilityRule $sourceRule,
        Carbon $targetDate,
        string $startsAt,
        string $endsAt,
        bool $repeats,
        ?string $repeatEndsOn,
        array $excludedDates = [],
    ): BookingAvailabilityRule {
        $serviceIds = $sourceRule->services()
            ->pluck('services.id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($serviceIds === []) {
            $serviceIds = $this->normalizeServiceIds($sourceRule->service_ids ?? []);
        }

        $newRule = BookingAvailabilityRule::query()->create([
            'branch_id' => $sourceRule->branch_id,

            'date' => $targetDate->toDateString(),
            'day_of_week' => $targetDate->dayOfWeekIso,

            'starts_at' => $startsAt,
            'ends_at' => $endsAt,

            'slot_mode' => 'free_bookable_time',
            'service_id' => null,
            'service_ids' => $serviceIds,
            'bookable_places' => 1,

            'repeats' => $repeats,
            'repeat_every' => $repeats ? (int) ($sourceRule->repeat_every ?: 1) : 1,
            'repeat_unit' => $repeats ? ($sourceRule->repeat_unit ?: 'weeks') : 'weeks',
            'repeat_ends_on' => $repeats ? $repeatEndsOn : null,

            'excluded_dates' => $repeats
                ? $this->normalizeDateStrings($excludedDates)
                : [],

            'is_enabled' => (bool) $sourceRule->is_enabled,
        ]);

        $newRule->services()->sync($serviceIds);

        return $newRule;
    }

    private function normalizeServiceIds(array $serviceIds): array
    {
        return collect($serviceIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeDateStrings(array $dates): array
    {
        return collect($dates)
            ->filter()
            ->map(fn ($date): string => Carbon::parse($date)->startOfDay()->toDateString())
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}