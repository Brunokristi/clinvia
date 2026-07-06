<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchDisabledDay;
use App\Services\DisabledDayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BranchDisabledDayController extends Controller
{
    private const TYPE_HOLIDAY_OPEN = 'holiday_open';

    public function index(Request $request, Branch $branch, DisabledDayService $disabledDayService): JsonResponse|RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        if (! Schema::hasTable('branch_disabled_days')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'data' => [],
                ]);
            }

            return back();
        }

        if (! $request->wantsJson()) {
            return back();
        }

        $validated = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        $rangeStart = isset($validated['start'])
            ? Carbon::parse($validated['start'])
            : now()->copy()->startOfYear();

        $rangeEnd = isset($validated['end'])
            ? Carbon::parse($validated['end'])
            : now()->copy()->addYear()->endOfYear();

        return response()->json([
            'data' => $disabledDayService->getDisabledDaysForRange($branch, $rangeStart, $rangeEnd)->values(),
        ]);
    }

    public function store(Request $request, Branch $branch, DisabledDayService $disabledDayService): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        if (! Schema::hasTable('branch_disabled_days')) {
            return back()->with('warning', 'Zakázané dni zatiaľ nie sú dostupné. Je potrebné spustiť migrácie.');
        }

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $type = $validated['type'] ?? 'closed';

        if ($type === self::TYPE_HOLIDAY_OPEN) {
            BranchDisabledDay::query()->updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'date' => $date,
                ],
                [
                    'created_by' => $request->user()->id,
                    'title' => $validated['title'] ?? 'Otvoreny sviatok',
                    'type' => self::TYPE_HOLIDAY_OPEN,
                    'reason' => $validated['reason'] ?? null,
                ],
            );

            return back()->with('success', 'Deň bol otvorený (výnimka počas sviatku).');
        }

        if (! filled($validated['title'] ?? null)) {
            throw ValidationException::withMessages([
                'title' => 'Nazov je povinny.',
            ]);
        }

        $eventCount = $disabledDayService->eventCountOnDate($branch, $validated['date']);

        if ($eventCount > 0) {
            throw ValidationException::withMessages([
                'date' => 'Tento deň nie je možné zatvoriť, pretože už obsahuje kalendárové udalosti.',
            ]);
        }

        BranchDisabledDay::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'date' => $date,
            ],
            [
                'created_by' => $request->user()->id,
                'title' => $validated['title'],
                'type' => $type,
                'reason' => $validated['reason'] ?? null,
            ],
        );

        return back()->with('success', 'Deň bol zatvorený.');
    }

    public function update(Request $request, Branch $branch, int $disabledDay, DisabledDayService $disabledDayService): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        if (! Schema::hasTable('branch_disabled_days')) {
            return back()->with('warning', 'Zakázané dni zatiaľ nie sú dostupné. Je potrebné spustiť migrácie.');
        }

        $disabledDayModel = BranchDisabledDay::query()
            ->where('branch_id', $branch->id)
            ->findOrFail($disabledDay);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $disabledDayModel->update([
            'date' => Carbon::parse($validated['date'])->toDateString(),
            'title' => $validated['title'],
            'type' => $validated['type'] ?? null,
            'reason' => $validated['reason'] ?? null,
        ]);

        $bookingCount = $disabledDayService->bookingCountOnDate($branch, $disabledDayModel->date);

        return back()->with([
            'success' => 'Deň bol upravený.',
            'warning' => $bookingCount > 0
                ? "Tento deň už obsahuje {$bookingCount} rezervácií. Neboli zmazané."
                : null,
        ]);
    }

    public function destroy(Request $request, Branch $branch, int $disabledDay): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        if (! Schema::hasTable('branch_disabled_days')) {
            return back()->with('warning', 'Zakázané dni zatiaľ nie sú dostupné. Je potrebné spustiť migrácie.');
        }

        $disabledDayModel = BranchDisabledDay::query()
            ->where('branch_id', $branch->id)
            ->findOrFail($disabledDay);

        $disabledDayModel->delete();

        return back()->with('success', 'Deň bol otvorený.');
    }
}
