<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BranchOpeningHoursController extends Controller
{
    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'opening_hours' => ['required', 'array', 'size:7'],
            'opening_hours.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'opening_hours.*.is_closed' => ['required', 'boolean'],
            'opening_hours.*.note' => ['nullable', 'string'],
            'opening_hours.*.sort_order' => ['nullable', 'integer'],
            'opening_hours.*.intervals' => ['nullable', 'array'],
            'opening_hours.*.intervals.*.opens_at' => ['required', 'date_format:H:i'],
            'opening_hours.*.intervals.*.closes_at' => ['required', 'date_format:H:i'],
            'opening_hours.*.intervals.*.sort_order' => ['nullable', 'integer'],
        ]);

        $validator->after(function ($validator) use ($request) {
            foreach ($request->input('opening_hours', []) as $dayIndex => $day) {
                if (! empty($day['is_closed'])) {
                    continue;
                }

                $intervals = $day['intervals'] ?? [];

                if (count($intervals) === 0) {
                    $validator->errors()->add(
                        "opening_hours.$dayIndex.intervals",
                        'Otvorený deň musí mať aspoň jeden časový interval.'
                    );

                    continue;
                }

                foreach ($intervals as $intervalIndex => $interval) {
                    if (($interval['opens_at'] ?? null) >= ($interval['closes_at'] ?? null)) {
                        $validator->errors()->add(
                            "opening_hours.$dayIndex.intervals.$intervalIndex.closes_at",
                            'Čas zatvorenia musí byť neskôr ako čas otvorenia.'
                        );
                    }
                }
            }
        });

        $data = $validator->validate();

        DB::transaction(function () use ($branch, $data) {
            $branch->openingHours()
                ->with('intervals')
                ->get()
                ->each(function ($openingHour) {
                    $openingHour->intervals()->delete();
                    $openingHour->delete();
                });

            foreach ($data['opening_hours'] as $day) {
                $openingHour = $branch->openingHours()->create([
                    'day_of_week' => $day['day_of_week'],
                    'is_closed' => $day['is_closed'],
                    'note' => $day['note'] ?? null,
                    'sort_order' => $day['sort_order'] ?? $day['day_of_week'],
                ]);

                if ($day['is_closed']) {
                    continue;
                }

                foreach ($day['intervals'] ?? [] as $interval) {
                    $openingHour->intervals()->create([
                        'opens_at' => $interval['opens_at'],
                        'closes_at' => $interval['closes_at'],
                        'sort_order' => $interval['sort_order'] ?? 0,
                    ]);
                }
            }
        });

        return back()->with('success', 'Otváracie hodiny boli uložené.');
    }
}