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

class BranchAvailabilityRuleController extends Controller
{
    public function sync(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $validated = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.id' => ['nullable', 'integer', 'exists:booking_availability_rules,id'],
            'rules.*.date' => ['required', 'date'],
            'rules.*.starts_at' => ['required', 'date_format:H:i'],
            'rules.*.ends_at' => ['required', 'date_format:H:i'],
            'rules.*.slot_mode' => ['nullable', 'in:free_bookable_time'],
            'rules.*.service_ids' => ['nullable', 'array'],
            'rules.*.service_ids.*' => ['integer', 'exists:services,id'],
            'rules.*.repeats' => ['required', 'boolean'],
            'rules.*.repeat_every' => ['required', 'integer', 'min:1'],
            'rules.*.repeat_unit' => ['required', 'in:days,weeks,months'],
            'rules.*.repeat_ends_on' => ['nullable', 'date'],
            'rules.*.excluded_dates' => ['nullable', 'array'],
            'rules.*.excluded_dates.*' => ['date'],
            'rules.*.is_enabled' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($branch, $validated): void {
            $keepIds = [];

            foreach ($validated['rules'] as $ruleData) {
                $existingRule = null;

                if (! empty($ruleData['id'])) {
                    $existingRule = BookingAvailabilityRule::query()
                        ->where('branch_id', $branch->id)
                        ->whereKey($ruleData['id'])
                        ->first();
                }

                $serviceIds = collect($ruleData['service_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $rule = BookingAvailabilityRule::query()->updateOrCreate(
                    [
                        'id' => $ruleData['id'] ?? null,
                        'branch_id' => $branch->id,
                    ],
                    [
                        'date' => $ruleData['date'],
                        'day_of_week' => Carbon::parse($ruleData['date'])->dayOfWeekIso,
                        'starts_at' => $ruleData['starts_at'],
                        'ends_at' => $ruleData['ends_at'],
                        'slot_mode' => 'free_bookable_time',
                        'service_id' => null,
                        'service_ids' => $serviceIds,
                        'bookable_places' => 1,
                        'repeats' => $ruleData['repeats'],
                        'repeat_every' => $ruleData['repeats'] ? $ruleData['repeat_every'] : 1,
                        'repeat_unit' => $ruleData['repeats'] ? $ruleData['repeat_unit'] : 'weeks',
                        'repeat_ends_on' => array_key_exists('repeat_ends_on', $ruleData)
                            ? $ruleData['repeat_ends_on']
                            : $existingRule?->repeat_ends_on,
                        'excluded_dates' => array_key_exists('excluded_dates', $ruleData)
                            ? ($ruleData['excluded_dates'] ?? [])
                            : ($existingRule?->excluded_dates ?? []),
                        'is_enabled' => $ruleData['is_enabled'],
                    ],
                );

                $rule->services()->sync($serviceIds);

                $keepIds[] = $rule->id;
            }

            BookingAvailabilityRule::query()
                ->where('branch_id', $branch->id)
                ->whereNotIn('id', $keepIds)
                ->get()
                ->each(function (BookingAvailabilityRule $rule): void {
                    $rule->services()->detach();
                    $rule->delete();
                });
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'availability_rules_updated',
        );

        return back()->with('success', 'Pravidlá dostupnosti boli uložené.');
    }

    public function deleteSeries(
        Request $request,
        Branch $branch,
        BookingAvailabilityRule $rule,
    ): RedirectResponse {
        $this->authorizeRule($request, $branch, $rule);

        DB::transaction(function () use ($rule): void {
            $rule->services()->detach();
            $rule->delete();
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'availability_rule_deleted',
        );

        return back()->with('success', 'Pravidlo bolo vymazané.');
    }

    public function deleteOccurrence(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
        $this->authorizeRule($request, $branch, $rule);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $dateString = Carbon::parse($validated['date'])->toDateString();

        DB::transaction(function () use ($rule, $dateString): void {
            if (! $rule->repeats) {
                $rule->services()->detach();
                $rule->delete();

                return;
            }

            $excludedDates = collect($rule->excluded_dates ?? [])
                ->push($dateString)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $rule->update([
                'excluded_dates' => $excludedDates,
            ]);
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'availability_rule_occurrence_deleted',
        );

        return back()->with('success', 'Tento deň bol vymazaný z opakovania.');
    }

    public function deleteFutureOccurrences(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
        $this->authorizeRule($request, $branch, $rule);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        DB::transaction(function () use ($rule, $date): void {
            if (! $rule->repeats || $date->lessThanOrEqualTo(Carbon::parse($rule->date)->startOfDay())) {
                $rule->services()->detach();
                $rule->delete();

                return;
            }

            $rule->update([
                'repeat_ends_on' => $date->copy()->subDay()->toDateString(),
            ]);
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'availability_rule_future_deleted',
        );

        return back()->with('success', 'Opakovanie bolo ukončené.');
    }

    public function reschedule(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
        $this->authorizeRule($request, $branch, $rule);

        $validated = $request->validate([
            'occurrence_date' => ['required', 'date'],
            'date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'reschedule_scope' => ['required', 'in:occurrence,from_date,series'],
        ]);

        $scope = $validated['reschedule_scope'];

        if (! $rule->repeats) {
            $scope = 'series';
        }

        if (
            $scope === 'from_date'
            && Carbon::parse($validated['occurrence_date'])->startOfDay()
                ->lessThanOrEqualTo(Carbon::parse($rule->date)->startOfDay())
        ) {
            $scope = 'series';
        }

        DB::transaction(function () use ($rule, $validated, $scope): void {
            if ($scope === 'series') {
                $rule->update([
                    'date' => Carbon::parse($validated['date'])->toDateString(),
                    'day_of_week' => Carbon::parse($validated['date'])->dayOfWeekIso,
                    'starts_at' => $validated['starts_at'],
                    'ends_at' => $validated['ends_at'],
                ]);

                return;
            }

            if ($scope === 'occurrence') {
                $excludedDates = collect($rule->excluded_dates ?? [])
                    ->push(Carbon::parse($validated['occurrence_date'])->toDateString())
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $rule->update([
                    'excluded_dates' => $excludedDates,
                ]);

                $newRule = $rule->replicate();
                $newRule->date = Carbon::parse($validated['date'])->toDateString();
                $newRule->day_of_week = Carbon::parse($validated['date'])->dayOfWeekIso;
                $newRule->starts_at = $validated['starts_at'];
                $newRule->ends_at = $validated['ends_at'];
                $newRule->repeats = false;
                $newRule->repeat_every = 1;
                $newRule->repeat_unit = 'weeks';
                $newRule->repeat_ends_on = null;
                $newRule->excluded_dates = [];
                $newRule->save();

                $newRule->services()->sync($rule->services()->pluck('services.id')->all());

                return;
            }

            $oldRepeatEndsOn = $rule->repeat_ends_on;
            $occurrenceDate = Carbon::parse($validated['occurrence_date'])->startOfDay();

            $rule->update([
                'repeat_ends_on' => $occurrenceDate->copy()->subDay()->toDateString(),
            ]);

            $newRule = $rule->replicate();
            $newRule->date = Carbon::parse($validated['date'])->toDateString();
            $newRule->day_of_week = Carbon::parse($validated['date'])->dayOfWeekIso;
            $newRule->starts_at = $validated['starts_at'];
            $newRule->ends_at = $validated['ends_at'];
            $newRule->repeat_ends_on = $oldRepeatEndsOn;
            $newRule->excluded_dates = [];
            $newRule->save();

            $newRule->services()->sync($rule->services()->pluck('services.id')->all());
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: match ($scope) {
                'occurrence' => 'availability_rule_occurrence_rescheduled',
                'from_date' => 'availability_rule_rescheduled_from_date',
                default => 'availability_rule_rescheduled',
            },
        );

        return back()->with(
            'success',
            match ($scope) {
                'occurrence' => 'Tento výskyt dostupnosti bol presunutý.',
                'from_date' => 'Tento a nasledujúce výskyty dostupnosti boli presunuté.',
                default => 'Dostupnosť bola presunutá.',
            },
        );
    }

    private function authorizeRule(Request $request, Branch $branch, BookingAvailabilityRule $rule): void
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);
    }
}
