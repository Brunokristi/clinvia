<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateBookingAction;
use App\Events\BranchCalendarUpdated;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CapacityWindow;
use App\Models\Service;
use App\Services\AdminBookingNotificationService;
use App\Services\CapacityWindowService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BranchCapacityWindowController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1'],
            'admin_note' => ['nullable', 'string'],

            'repeats' => ['nullable', 'boolean'],
            'repeat_every' => ['nullable', 'integer', 'min:1'],
            'repeat_unit' => ['nullable', 'in:days,weeks,months'],
            'repeat_ends_on' => ['nullable', 'required_if:repeats,true', 'date'],
        ]);

        $service = $this->resolveBranchService($branch, (int) $validated['service_id']);

        $createdCount = DB::transaction(function () use ($branch, $service, $validated): int {
            $startsAt = Carbon::parse($validated['starts_at']);
            $endsAt = Carbon::parse($validated['ends_at']);

            if (! ($validated['repeats'] ?? false)) {
                $this->createWindow($branch, $service, $startsAt, $endsAt, $validated, null);

                return 1;
            }

            if (blank($validated['repeat_ends_on'] ?? null)) {
                throw ValidationException::withMessages([
                    'repeat_ends_on' => 'Vyberte dátum ukončenia opakovania.',
                ]);
            }

            $repeatEvery = max(1, (int) ($validated['repeat_every'] ?? 1));
            $repeatUnit = $validated['repeat_unit'] ?? 'weeks';
            $repeatEndsOn = Carbon::parse($validated['repeat_ends_on'])->endOfDay();

            if ($repeatEndsOn->lt($startsAt->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    'repeat_ends_on' => 'Dátum ukončenia opakovania nemôže byť pred prvým termínom.',
                ]);
            }

            $seriesUuid = (string) Str::uuid();
            $cursorStart = $startsAt->copy();
            $cursorEnd = $endsAt->copy();
            $createdCount = 0;

            while ($cursorStart->lte($repeatEndsOn)) {
                $this->createWindow($branch, $service, $cursorStart, $cursorEnd, $validated, $seriesUuid);

                $createdCount++;

                if ($createdCount > 370) {
                    throw ValidationException::withMessages([
                        'repeat_ends_on' => 'Opakovanie je príliš dlhé. Skráťte dátum ukončenia.',
                    ]);
                }

                match ($repeatUnit) {
                    'days' => $this->addDays($cursorStart, $cursorEnd, $repeatEvery),
                    'months' => $this->addMonths($cursorStart, $cursorEnd, $repeatEvery),
                    default => $this->addWeeks($cursorStart, $cursorEnd, $repeatEvery),
                };
            }

            return $createdCount;
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'capacity_window_created',
        );

        return back()->with(
            'success',
            $createdCount > 1
                ? "Séria skupinových termínov bola vytvorená ({$createdCount} termínov)."
                : 'Skupinový termín bol vytvorený.',
        );
    }

    public function update(Request $request, Branch $branch, CapacityWindow $capacityWindow): RedirectResponse
    {
        $this->authorizeCapacityWindow($request, $branch, $capacityWindow);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            'admin_note' => ['nullable', 'string'],
            'apply_to_series' => ['nullable', 'boolean'],
        ]);

        $service = $this->resolveBranchService($branch, (int) $validated['service_id']);

        DB::transaction(function () use ($capacityWindow, $service, $validated): void {
            $applyToSeries = ($validated['apply_to_series'] ?? false) && filled($capacityWindow->series_uuid);

            $query = CapacityWindow::query()
                ->where('branch_id', $capacityWindow->branch_id);

            if ($applyToSeries) {
                $query->where('series_uuid', $capacityWindow->series_uuid);
            } else {
                $query->whereKey($capacityWindow->id);
            }

            $windows = $query->lockForUpdate()->get();

            foreach ($windows as $window) {
                $activeBookingsCount = $window->bookings()
                    ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
                    ->count();

                if ((int) $validated['capacity'] < $activeBookingsCount) {
                    throw ValidationException::withMessages([
                        'capacity' => 'Kapacita nemôže byť menšia ako počet existujúcich rezervácií.',
                    ]);
                }

                $window->update([
                    'service_id' => $service->id,
                    'capacity' => (int) $validated['capacity'],
                    'admin_note' => $validated['admin_note'] ?? null,
                ]);
            }
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'capacity_window_updated',
            capacityWindowId: $capacityWindow->id,
        );

        return back()->with('success', 'Skupinový termín bol upravený.');
    }

    public function reschedule(
        Request $request,
        Branch $branch,
        CapacityWindow $capacityWindow,
        CapacityWindowService $capacityWindowService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        $this->authorizeCapacityWindow($request, $branch, $capacityWindow);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reschedule_scope' => ['nullable', 'in:occurrence,from_date,series'],
            'from_date' => ['nullable', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $scope = $validated['reschedule_scope'] ?? 'occurrence';

        if ($scope !== 'occurrence' && blank($capacityWindow->series_uuid)) {
            $scope = 'occurrence';
        }

        $newStartsAt = Carbon::parse($validated['starts_at']);
        $newEndsAt = Carbon::parse($validated['ends_at']);
        $notifyPatient = $request->boolean('notify_patient', true);
        $reason = $validated['notification_reason'] ?? null;

        if ($scope === 'occurrence') {
            $capacityWindowService->rescheduleWindow(
                capacityWindow: $capacityWindow,
                newStartsAt: $newStartsAt,
                newEndsAt: $newEndsAt,
                notifyPatient: $notifyPatient,
                reason: $reason,
                notificationService: $notificationService,
            );
        } else {
            $this->rescheduleCapacityWindowSeries(
                capacityWindow: $capacityWindow,
                scope: $scope,
                fromDate: $validated['from_date'] ?? null,
                newStartsAt: $newStartsAt,
                newEndsAt: $newEndsAt,
                notifyPatient: $notifyPatient,
                reason: $reason,
                capacityWindowService: $capacityWindowService,
                notificationService: $notificationService,
            );
        }

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: match ($scope) {
                'from_date' => 'capacity_window_series_rescheduled_from_date',
                'series' => 'capacity_window_series_rescheduled',
                default => 'capacity_window_rescheduled',
            },
            capacityWindowId: $capacityWindow->id,
        );

        return back()->with(
            'success',
            match ($scope) {
                'from_date' => 'Tento a nasledujúce skupinové termíny boli presunuté.',
                'series' => 'Celá séria skupinových termínov bola presunutá.',
                default => 'Skupinový termín bol presunutý.',
            },
        );
    }

    public function cancel(
        Request $request,
        Branch $branch,
        CapacityWindow $capacityWindow,
        CapacityWindowService $capacityWindowService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        $this->authorizeCapacityWindow($request, $branch, $capacityWindow);

        $validated = $request->validate([
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $capacityWindowService->cancelWindow(
            capacityWindow: $capacityWindow,
            notifyPatient: $request->boolean('notify_patient', true),
            reason: $validated['notification_reason'] ?? null,
            notificationService: $notificationService,
        );

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'capacity_window_cancelled',
            capacityWindowId: $capacityWindow->id,
        );

        return back()->with('success', 'Skupinový termín bol zrušený.');
    }

    public function destroy(
        Request $request,
        Branch $branch,
        CapacityWindow $capacityWindow,
        CapacityWindowService $capacityWindowService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        $this->authorizeCapacityWindow($request, $branch, $capacityWindow);

        $validated = $request->validate([
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $capacityWindowService->cancelWindow(
            capacityWindow: $capacityWindow,
            notifyPatient: $request->boolean('notify_patient', true),
            reason: $validated['notification_reason'] ?? null,
            notificationService: $notificationService,
        );

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'capacity_window_deleted',
            capacityWindowId: $capacityWindow->id,
        );

        return back()->with('success', 'Skupinový termín bol vymazaný.');
    }

    public function destroySeries(
        Request $request,
        Branch $branch,
        CapacityWindow $capacityWindow,
        CapacityWindowService $capacityWindowService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        $this->authorizeCapacityWindow($request, $branch, $capacityWindow);

        $validated = $request->validate([
            'delete_scope' => ['nullable', 'in:series,from_date'],
            'from_date' => ['nullable', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (blank($capacityWindow->series_uuid)) {
            return $this->destroy(
                request: $request,
                branch: $branch,
                capacityWindow: $capacityWindow,
                capacityWindowService: $capacityWindowService,
                notificationService: $notificationService,
            );
        }

        $deleteScope = $validated['delete_scope'] ?? 'series';
        $fromDate = $deleteScope === 'from_date'
            ? Carbon::parse($validated['from_date'] ?? $capacityWindow->starts_at)->startOfDay()
            : null;

        $windowsQuery = CapacityWindow::query()
            ->where('branch_id', $capacityWindow->branch_id)
            ->where('series_uuid', $capacityWindow->series_uuid)
            ->where('status', 'active');

        if ($fromDate) {
            $windowsQuery->where('starts_at', '>=', $fromDate);
        }

        $windows = $windowsQuery
            ->orderBy('starts_at')
            ->get();

        foreach ($windows as $window) {
            $capacityWindowService->cancelWindow(
                capacityWindow: $window,
                notifyPatient: $request->boolean('notify_patient', true),
                reason: $validated['notification_reason'] ?? null,
                notificationService: $notificationService,
            );
        }

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: $fromDate ? 'capacity_window_series_deleted_from_date' : 'capacity_window_series_deleted',
            capacityWindowId: $capacityWindow->id,
        );

        return back()->with(
            'success',
            $fromDate
                ? 'Skupinové termíny od vybraného dátumu boli vymazané.'
                : 'Séria skupinových termínov bola vymazaná.',
        );
    }

    public function storeBooking(
        Request $request,
        Branch $branch,
        CapacityWindow $capacityWindow,
        CreateBookingAction $createBookingAction,
    ): RedirectResponse {
        $this->authorizeCapacityWindow($request, $branch, $capacityWindow);

        $validated = $request->validate([
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_note' => ['nullable', 'string', 'max:2000'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'notify_patient' => ['nullable', 'boolean'],
        ]);

        $createBookingAction->execute($branch, [
            'capacity_window_id' => $capacityWindow->id,
            'service_id' => $capacityWindow->service_id,
            'starts_at' => $capacityWindow->starts_at,
            'ends_at' => $capacityWindow->ends_at,
            'patient_name' => $validated['patient_name'],
            'patient_email' => $validated['patient_email'] ?? null,
            'patient_phone' => $validated['patient_phone'] ?? null,
            'patient_note' => $validated['patient_note'] ?? null,
            'admin_note' => $validated['admin_note'] ?? null,
            'status' => 'confirmed',
            'notify_patient' => $request->boolean('notify_patient', true),
        ]);

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'capacity_window_booking_created',
            capacityWindowId: $capacityWindow->id,
        );

        return back()->with('success', 'Pacient bol pridaný do skupinového termínu.');
    }

    private function rescheduleCapacityWindowSeries(
        CapacityWindow $capacityWindow,
        string $scope,
        ?string $fromDate,
        Carbon $newStartsAt,
        Carbon $newEndsAt,
        bool $notifyPatient,
        ?string $reason,
        CapacityWindowService $capacityWindowService,
        AdminBookingNotificationService $notificationService,
    ): void {
        $originalStartsAt = $capacityWindow->starts_at->copy();
        $newDurationMinutes = $newStartsAt->diffInMinutes($newEndsAt);

        $dayDiff = $originalStartsAt
            ->copy()
            ->startOfDay()
            ->diffInDays($newStartsAt->copy()->startOfDay(), false);

        $minuteOfDayDiff = (($newStartsAt->hour * 60) + $newStartsAt->minute)
            - (($originalStartsAt->hour * 60) + $originalStartsAt->minute);

        $windowsQuery = CapacityWindow::query()
            ->where('branch_id', $capacityWindow->branch_id)
            ->where('series_uuid', $capacityWindow->series_uuid)
            ->where('status', 'active');

        if ($scope === 'from_date') {
            $windowsQuery->where(
                'starts_at',
                '>=',
                Carbon::parse($fromDate ?? $capacityWindow->starts_at)->startOfDay(),
            );
        }

        $windows = $windowsQuery
            ->orderBy('starts_at')
            ->get();

        foreach ($windows as $window) {
            $updatedStartsAt = $window->starts_at
                ->copy()
                ->addDays($dayDiff)
                ->addMinutes($minuteOfDayDiff);

            $updatedEndsAt = $updatedStartsAt->copy()->addMinutes($newDurationMinutes);

            $capacityWindowService->rescheduleWindow(
                capacityWindow: $window,
                newStartsAt: $updatedStartsAt,
                newEndsAt: $updatedEndsAt,
                notifyPatient: $notifyPatient,
                reason: $reason,
                notificationService: $notificationService,
            );
        }
    }

    private function authorizeCapacityWindow(Request $request, Branch $branch, CapacityWindow $capacityWindow): void
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $capacityWindow->branch_id !== (int) $branch->id, 404);
    }

    private function resolveBranchService(Branch $branch, int $serviceId): Service
    {
        return Service::query()
            ->where('branch_id', $branch->id)
            ->whereKey($serviceId)
            ->firstOrFail();
    }

    private function createWindow(
        Branch $branch,
        Service $service,
        Carbon $startsAt,
        Carbon $endsAt,
        array $validated,
        ?string $seriesUuid,
    ): void {
        CapacityWindow::query()->create([
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'series_uuid' => $seriesUuid,
            'starts_at' => $startsAt->copy(),
            'ends_at' => $endsAt->copy(),
            'capacity' => (int) $validated['capacity'],
            'status' => 'active',
            'admin_note' => $validated['admin_note'] ?? null,
        ]);
    }

    private function addDays(Carbon $startsAt, Carbon $endsAt, int $days): void
    {
        $startsAt->addDays($days);
        $endsAt->addDays($days);
    }

    private function addWeeks(Carbon $startsAt, Carbon $endsAt, int $weeks): void
    {
        $startsAt->addWeeks($weeks);
        $endsAt->addWeeks($weeks);
    }

    private function addMonths(Carbon $startsAt, Carbon $endsAt, int $months): void
    {
        $startsAt->addMonths($months);
        $endsAt->addMonths($months);
    }
}
