<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingAvailabilityRule;
use App\Models\Branch;
use App\Models\Service;
use App\Services\BookingSlotGenerator;
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
            'rules.*.slot_mode' => ['required', 'in:single_service_many_clients,free_bookable_time'],
            'rules.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'rules.*.service_ids' => ['nullable', 'array'],
            'rules.*.service_ids.*' => ['integer', 'exists:services,id'],
            'rules.*.bookable_places' => ['required', 'integer', 'min:1'],
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

                $dayOfWeek = Carbon::parse($ruleData['date'])->dayOfWeekIso;

                $serviceIds = $ruleData['slot_mode'] === 'single_service_many_clients'
                    ? array_values(array_filter([$ruleData['service_id'] ?? null]))
                    : array_values($ruleData['service_ids'] ?? []);

                $rule = BookingAvailabilityRule::updateOrCreate(
                    [
                        'id' => $ruleData['id'] ?? null,
                        'branch_id' => $branch->id,
                    ],
                    [
                        'date' => $ruleData['date'],
                        'day_of_week' => $dayOfWeek,
                        'starts_at' => $ruleData['starts_at'],
                        'ends_at' => $ruleData['ends_at'],
                        'slot_mode' => $ruleData['slot_mode'],
                        'service_id' => $ruleData['slot_mode'] === 'single_service_many_clients'
                            ? ($ruleData['service_id'] ?? null)
                            : null,
                        'service_ids' => $serviceIds,
                        'bookable_places' => $ruleData['slot_mode'] === 'single_service_many_clients'
                            ? $ruleData['bookable_places']
                            : 1,
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
                ->delete();
        });

        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Pravidlá dostupnosti boli uložené.');
    }

    public function deleteSeries(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $ruleStartDate = $rule->date
            ? Carbon::parse($rule->date)->startOfDay()
            : now()->startOfDay();

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleFromDate($rule, $ruleStartDate);

        $rule->services()->detach();
        $rule->delete();

        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Pravidlo bolo vymazané.');
    }

    public function deleteOccurrence(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        if (! $rule->repeats) {
            app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleFromDate($rule, $date);

            $rule->services()->detach();
            $rule->delete();

            app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

            return back()->with('success', 'Pravidlo bolo vymazané.');
        }

        $dateString = $date->toDateString();

        $excludedDates = collect($rule->excluded_dates ?? [])
            ->push($dateString)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $rule->update([
            'excluded_dates' => $excludedDates,
        ]);

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleDate($rule, $date);
        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Tento deň bol vymazaný z opakovania.');
    }

    public function deleteFutureOccurrences(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        if (! $rule->repeats) {
            app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleFromDate($rule, $date);

            $rule->services()->detach();
            $rule->delete();

            app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

            return back()->with('success', 'Pravidlo bolo vymazané.');
        }

        $rule->update([
            'repeat_ends_on' => $date->copy()->subDay()->toDateString(),
        ]);

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleFromDate($rule, $date);
        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Opakovanie bolo ukončené.');
    }
}
