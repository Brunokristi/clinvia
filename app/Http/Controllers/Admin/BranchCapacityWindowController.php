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
use App\Services\DisabledDayService;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BranchCapacityWindowController extends Controller
{
    private const MAX_RECURRING_OCCURRENCES = 370;

    public function store(Request $request, Branch $branch, CreateBookingAction $createBookingAction): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $recurrenceService = app(RecurrenceService::class);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1'],
            'admin_note' => ['nullable', 'string'],
            'public_booking_type' => ['nullable', 'in:appointment_request,immediate_booking'],

            'repeats' => ['nullable', 'boolean'],
            'repeat_every' => ['nullable', 'integer', 'min:1'],
            'repeat_unit' => ['nullable', 'in:days,weeks,months'],
            'repeat_ends_on' => ['nullable', 'date'],

            'recurrence' => ['nullable', 'array'],
            'recurrence.frequency' => ['required_with:recurrence', 'in:daily,weekly,monthly,yearly'],
            'recurrence.interval' => ['nullable', 'integer', 'min:1'],
            'recurrence.weekdays' => ['nullable', 'array'],
            'recurrence.weekdays.*' => ['in:MO,TU,WE,TH,FR,SA,SU'],
            'recurrence.ends' => ['nullable', 'array'],
            'recurrence.ends.type' => ['required_with:recurrence.ends', 'in:never,on,after'],
            'recurrence.ends.count' => ['nullable', 'integer', 'min:1'],
            'recurrence.ends.until' => ['nullable', 'date'],

            'patients' => ['nullable', 'array'],
            'patients.*.patient_name' => ['required_with:patients', 'string', 'max:255'],
            'patients.*.patient_email' => ['nullable', 'email', 'max:255'],
            'patients.*.patient_phone' => ['nullable', 'string', 'max:255'],

            // Compatibility alias used by older/newer frontend flows.
            'group_patients' => ['nullable', 'array'],
            'group_patients.*.patient_name' => ['required_with:group_patients', 'string', 'max:255'],
            'group_patients.*.patient_email' => ['nullable', 'email', 'max:255'],
            'group_patients.*.patient_phone' => ['nullable', 'string', 'max:255'],
        ]);

        $rawPatients = collect($validated['patients'] ?? []);

        if ($rawPatients->isEmpty()) {
            $rawPatients = collect($validated['group_patients'] ?? []);
        }

        $patients = $rawPatients
            ->filter(fn (array $patient): bool => filled($patient['patient_name'] ?? null))
            ->values();

        $hasPatientPayload = $request->exists('patients') || $request->exists('group_patients');

        if ($patients->count() > (int) $validated['capacity']) {
            throw ValidationException::withMessages([
                'patients' => 'Počet pacientov nemôže byť vyšší ako kapacita skupinového termínu.',
            ]);
        }

        if (($validated['repeats'] ?? false) && $patients->isNotEmpty()) {
            throw ValidationException::withMessages([
                'patients' => 'Pacientov pri vytvorení môžete pridať len pre jednorazový skupinový termín.',
            ]);
        }

        if (filled($validated['recurrence'] ?? null)) {
            $validated['recurrence'] = $recurrenceService->normalize($validated['recurrence']);
            $validated['repeats'] = true;
            $validated['repeat_every'] = $this->resolveRepeatEvery($validated);
            $validated['repeat_unit'] = $this->resolveRepeatUnit($validated);
            $validated['repeat_ends_on'] = $this->resolveRepeatEndsOn($validated);
        }

        $hasExplicitRepeatEnd = $this->hasExplicitRepeatEnd($validated);

        if (($validated['repeats'] ?? false) && blank($validated['repeat_ends_on'] ?? null)) {
            $validated['repeat_ends_on'] = Carbon::parse($validated['starts_at'])->addYears(2)->toDateString();
        }

        $service = $this->resolveBranchService($branch, (int) $validated['service_id']);
        $disabledDayService = app(DisabledDayService::class);

        if (filled($validated['public_booking_type'] ?? null)) {
            $service->update([
                'public_booking_type' => $validated['public_booking_type'],
            ]);
        }

        $createdCount = DB::transaction(function () use ($branch, $service, $validated, $disabledDayService, $patients, $createBookingAction, $hasExplicitRepeatEnd): int {
            $startsAt = Carbon::parse($validated['starts_at']);
            $endsAt = Carbon::parse($validated['ends_at']);

            if (! ($validated['repeats'] ?? false) && $disabledDayService->isDisabled($branch, $startsAt)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Tento deň je v kalendári zakázaný.',
                ]);
            }

            if (! ($validated['repeats'] ?? false)) {
                $capacityWindow = $this->createWindow($branch, $service, $startsAt, $endsAt, $validated, null);

                foreach ($patients as $patient) {
                    $createBookingAction->execute($branch, [
                        'capacity_window_id' => $capacityWindow->id,
                        'service_id' => $service->id,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'patient_name' => $patient['patient_name'],
                        'patient_email' => $patient['patient_email'] ?? null,
                        'patient_phone' => $patient['patient_phone'] ?? null,
                        'status' => 'confirmed',
                        'notify_patient' => true,
                    ]);
                }

                return 1;
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
            $createdCount = 0;

            foreach ($this->buildRecurringOccurrenceDateTimes($startsAt, $endsAt, $repeatEndsOn, $validated, $repeatEvery, $repeatUnit) as [$cursorStart, $cursorEnd]) {
                if ($createdCount >= self::MAX_RECURRING_OCCURRENCES) {
                    if ($hasExplicitRepeatEnd) {
                        throw ValidationException::withMessages([
                            'repeat_ends_on' => 'Opakovanie je príliš dlhé. Skráťte dátum ukončenia.',
                        ]);
                    }

                    break;
                }

                if ($disabledDayService->isDisabled($branch, $cursorStart)) {
                    continue;
                }

                $this->createWindow($branch, $service, $cursorStart, $cursorEnd, $validated, $seriesUuid);

                $createdCount++;
            }

            if ($createdCount === 0) {
                throw ValidationException::withMessages([
                    'repeat_ends_on' => 'V zadanom rozsahu opakovania nie je žiadny dostupný deň.',
                ]);
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

    public function update(
        Request $request,
        Branch $branch,
        CapacityWindow $capacityWindow,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse
    {
        $this->authorizeCapacityWindow($request, $branch, $capacityWindow);
        $recurrenceService = app(RecurrenceService::class);

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            'admin_note' => ['nullable', 'string'],
            'public_booking_type' => ['nullable', 'in:appointment_request,immediate_booking'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],

            /**
             * occurrence = only this one capacity window
             * from_date = this and following windows in the same series
             * series = all windows in the same series
             */
            'update_scope' => ['nullable', 'in:occurrence,from_date,series'],

            /**
             * Backwards compatibility with the old frontend.
             */
            'apply_to_series' => ['nullable', 'boolean'],

            'from_date' => ['nullable', 'date'],
            'sync_patients' => ['nullable', 'boolean'],

            'recurrence' => ['nullable', 'array'],
            'recurrence.frequency' => ['required_with:recurrence', 'in:daily,weekly,monthly,yearly'],
            'recurrence.interval' => ['nullable', 'integer', 'min:1'],
            'recurrence.weekdays' => ['nullable', 'array'],
            'recurrence.weekdays.*' => ['in:MO,TU,WE,TH,FR,SA,SU'],
            'recurrence.ends' => ['nullable', 'array'],
            'recurrence.ends.type' => ['required_with:recurrence.ends', 'in:never,on,after'],
            'recurrence.ends.count' => ['nullable', 'integer', 'min:1'],
            'recurrence.ends.until' => ['nullable', 'date'],

            'patients' => ['nullable', 'array'],
            'patients.*.patient_name' => ['required_with:patients', 'string', 'max:255'],
            'patients.*.patient_email' => ['nullable', 'email', 'max:255'],
            'patients.*.patient_phone' => ['nullable', 'string', 'max:255'],

            'group_patients' => ['nullable', 'array'],
            'group_patients.*.patient_name' => ['required_with:group_patients', 'string', 'max:255'],
            'group_patients.*.patient_email' => ['nullable', 'email', 'max:255'],
            'group_patients.*.patient_phone' => ['nullable', 'string', 'max:255'],
        ]);

        $rawPatients = collect($validated['patients'] ?? []);

        if ($rawPatients->isEmpty()) {
            $rawPatients = collect($validated['group_patients'] ?? []);
        }

        $patients = $rawPatients
            ->filter(fn (array $patient): bool => filled($patient['patient_name'] ?? null))
            ->values();

        $hasPatientPayload = $request->boolean('sync_patients')
            || $request->exists('patients')
            || $request->exists('group_patients');

        if (filled($validated['recurrence'] ?? null)) {
            $validated['recurrence'] = $recurrenceService->normalize($validated['recurrence']);
            $validated['update_scope'] = $validated['update_scope'] ?? 'series';
        }

        $hasExplicitRepeatEnd = $this->hasExplicitRepeatEnd($validated);

        $service = $this->resolveBranchService($branch, (int) $validated['service_id']);
        $disabledDayService = app(DisabledDayService::class);

        if (filled($validated['public_booking_type'] ?? null)) {
            $service->update([
                'public_booking_type' => $validated['public_booking_type'],
            ]);
        }

        if ($disabledDayService->isDisabled($branch, $capacityWindow->starts_at)) {
            throw ValidationException::withMessages([
                'service_id' => 'Tento deň je v kalendári zakázaný.',
            ]);
        }

        $resolvedScope = $this->resolveSeriesScope(
            requestedScope: $validated['update_scope'] ?? null,
            applyToSeries: $validated['apply_to_series'] ?? null,
            capacityWindow: $capacityWindow,
        );

        if ($hasPatientPayload && $resolvedScope === 'occurrence') {
            $validated['recurrence'] = null;
        }

        if ($hasPatientPayload && $resolvedScope !== 'occurrence') {
            throw ValidationException::withMessages([
                'patients' => 'Pacientov je možné pridať iba do jedného konkrétneho skupinového termínu.',
            ]);
        }

        DB::transaction(function () use ($branch, $capacityWindow, $service, $validated, $disabledDayService, $patients, $hasPatientPayload, $notificationService, $hasExplicitRepeatEnd): void {
            $scope = $this->resolveSeriesScope(
                requestedScope: $validated['update_scope'] ?? null,
                applyToSeries: $validated['apply_to_series'] ?? null,
                capacityWindow: $capacityWindow,
            );

            if (filled($validated['recurrence'] ?? null)) {
                if ($scope === 'occurrence') {
                    if (filled($capacityWindow->series_uuid)) {
                        $scope = 'from_date';
                        $validated['from_date'] = $validated['from_date']
                            ?? $capacityWindow->starts_at?->toDateString()
                            ?? null;
                    } else {
                        $capacityWindow->refresh();

                        $activeBookingsCount = $capacityWindow->bookings()
                            ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
                            ->count();

                        if ((int) $validated['capacity'] < $activeBookingsCount) {
                            throw ValidationException::withMessages([
                                'capacity' => 'Kapacita nemôže byť menšia ako počet existujúcich rezervácií.',
                            ]);
                        }

                        $seriesUuid = $capacityWindow->series_uuid ?: (string) Str::uuid();

                        $capacityWindow->update([
                            'service_id' => $service->id,
                            'capacity' => (int) $validated['capacity'],
                            'admin_note' => $validated['admin_note'] ?? null,
                            'series_uuid' => $seriesUuid,
                        ]);

                        $newStartsAt = Carbon::parse($validated['starts_at'] ?? $capacityWindow->starts_at);
                        $newEndsAt = Carbon::parse($validated['ends_at'] ?? $capacityWindow->ends_at);
                        $repeatEvery = $this->resolveRepeatEvery($validated);
                        $repeatUnit = $this->resolveRepeatUnit($validated);
                        $repeatEndsOn = $this->resolveRecurrenceSeriesEndDate($newStartsAt, $validated, $repeatEvery, $repeatUnit);

                        $cursorStart = $newStartsAt->copy();
                        $cursorEnd = $newEndsAt->copy();

                        match ($repeatUnit) {
                            'days' => $this->addDays($cursorStart, $cursorEnd, $repeatEvery),
                            'months' => $this->addMonths($cursorStart, $cursorEnd, $repeatEvery),
                            default => $this->addWeeks($cursorStart, $cursorEnd, $repeatEvery),
                        };

                        $createdCount = 0;

                        while ($cursorStart->lte($repeatEndsOn)) {
                            if ($createdCount >= self::MAX_RECURRING_OCCURRENCES) {
                                if ($hasExplicitRepeatEnd) {
                                    throw ValidationException::withMessages([
                                        'recurrence' => 'Opakovanie je príliš dlhé. Skráťte rozsah opakovania.',
                                    ]);
                                }

                                break;
                            }

                            if ($disabledDayService->isDisabled($branch, $cursorStart)) {
                                match ($repeatUnit) {
                                    'days' => $this->addDays($cursorStart, $cursorEnd, $repeatEvery),
                                    'months' => $this->addMonths($cursorStart, $cursorEnd, $repeatEvery),
                                    default => $this->addWeeks($cursorStart, $cursorEnd, $repeatEvery),
                                };

                                continue;
                            }

                            $this->createWindow(
                                branch: $branch,
                                service: $service,
                                startsAt: $cursorStart,
                                endsAt: $cursorEnd,
                                validated: $validated,
                                seriesUuid: $seriesUuid,
                            );

                            $createdCount++;

                            match ($repeatUnit) {
                                'days' => $this->addDays($cursorStart, $cursorEnd, $repeatEvery),
                                'months' => $this->addMonths($cursorStart, $cursorEnd, $repeatEvery),
                                default => $this->addWeeks($cursorStart, $cursorEnd, $repeatEvery),
                            };
                        }

                        if ($createdCount === 0) {
                            throw ValidationException::withMessages([
                                'recurrence' => 'V zadanom rozsahu opakovania nie je žiadny dostupný deň.',
                            ]);
                        }

                        return;
                    }
                }

                $this->rebuildRecurringSeriesForScope(
                    branch: $branch,
                    service: $service,
                    capacityWindow: $capacityWindow,
                    validated: $validated,
                    scope: $scope,
                    disabledDayService: $disabledDayService,
                    hasExplicitRepeatEnd: $hasExplicitRepeatEnd,
                );

                return;
            }

            $windows = $this->getCapacityWindowsForScope(
                capacityWindow: $capacityWindow,
                scope: $scope,
                fromDate: $validated['from_date'] ?? null,
                activeOnly: true,
            );

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

            if ($scope === 'occurrence' && filled($capacityWindow->series_uuid)) {
                $capacityWindow->refresh();

                if ($capacityWindow->status === 'active') {
                    $capacityWindow->update([
                        'series_uuid' => null,
                    ]);
                }
            }

            if ($scope === 'occurrence' && $hasPatientPayload) {
                $capacityWindow->refresh();

                $activeBookings = $capacityWindow->bookings()
                    ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
                    ->orderBy('id')
                    ->get();

                $normalizePatientKey = static function (?string $name, ?string $email, ?string $phone): string {
                    $normalizedName = mb_strtolower(trim((string) $name));
                    $normalizedEmail = mb_strtolower(trim((string) ($email ?? '')));
                    $normalizedPhone = preg_replace('/\s+/', '', (string) ($phone ?? ''));

                    return implode('|', [$normalizedName, $normalizedEmail, $normalizedPhone]);
                };

                $existingBuckets = [];

                foreach ($activeBookings as $booking) {
                    $key = $normalizePatientKey($booking->patient_name, $booking->patient_email, $booking->patient_phone);
                    $existingBuckets[$key] ??= [];
                    $existingBuckets[$key][] = $booking;
                }

                foreach ($patients as $patient) {
                    $key = $normalizePatientKey(
                        $patient['patient_name'] ?? null,
                        $patient['patient_email'] ?? null,
                        $patient['patient_phone'] ?? null,
                    );

                    if (! empty($existingBuckets[$key])) {
                        array_shift($existingBuckets[$key]);

                        continue;
                    }

                    app(CreateBookingAction::class)->execute($branch, [
                        'capacity_window_id' => $capacityWindow->id,
                        'service_id' => $capacityWindow->service_id,
                        'starts_at' => $capacityWindow->starts_at,
                        'ends_at' => $capacityWindow->ends_at,
                        'patient_name' => $patient['patient_name'],
                        'patient_email' => $patient['patient_email'] ?? null,
                        'patient_phone' => $patient['patient_phone'] ?? null,
                        'status' => 'confirmed',
                        'notify_patient' => true,
                    ]);
                }

                foreach ($existingBuckets as $remainingBookings) {
                    foreach ($remainingBookings as $bookingToCancel) {
                        $oldStatus = $bookingToCancel->status;

                        $bookingToCancel->update([
                            'status' => 'cancelled',
                        ]);

                        if ($oldStatus !== 'cancelled') {
                            $bookingToCancel->refresh()->load(['branch', 'service', 'services', 'capacityWindow']);
                            $notificationService->sendCancelledNotification($bookingToCancel);
                        }
                    }
                }
            }
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'capacity_window_updated',
            capacityWindowId: $capacityWindow->id,
        );

        return back()->with(
            'success',
            match ($validated['update_scope'] ?? null) {
                'from_date' => 'Tento a nasledujúce skupinové termíny boli upravené.',
                'series' => 'Celá séria skupinových termínov bola upravená.',
                default => 'Skupinový termín bol upravený.',
            },
        );
    }

    private function rebuildRecurringSeriesForScope(
        Branch $branch,
        Service $service,
        CapacityWindow $capacityWindow,
        array $validated,
        string $scope,
        DisabledDayService $disabledDayService,
        bool $hasExplicitRepeatEnd,
    ): void {
        $newStartsAt = Carbon::parse($validated['starts_at'] ?? $capacityWindow->starts_at);
        $newEndsAt = Carbon::parse($validated['ends_at'] ?? $capacityWindow->ends_at);

        if ($scope === 'from_date') {
            $fromDate = Carbon::parse($validated['from_date'] ?? $capacityWindow->starts_at)->startOfDay();

            if ($newStartsAt->copy()->startOfDay()->lt($fromDate)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Začiatok nového opakovania musí byť od vybraného dátumu.',
                ]);
            }
        }

        $windowsToReplace = $this->getCapacityWindowsForScope(
            capacityWindow: $capacityWindow,
            scope: $scope,
            fromDate: $validated['from_date'] ?? null,
            activeOnly: true,
        );

        foreach ($windowsToReplace as $window) {
            $activeBookingsCount = $window->bookings()
                ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
                ->count();

            if ($activeBookingsCount > 0) {
                throw ValidationException::withMessages([
                    'recurrence' => 'Opakovanie nie je možné zmeniť, pretože v dotknutých termínoch už sú rezervácie.',
                ]);
            }
        }

        foreach ($windowsToReplace as $window) {
            $window->update([
                'status' => 'cancelled',
            ]);
        }

        $seriesUuid = $capacityWindow->series_uuid ?: (string) Str::uuid();
        $repeatEvery = $this->resolveRepeatEvery($validated);
        $repeatUnit = $this->resolveRepeatUnit($validated);
        $repeatEndsOn = $this->resolveRecurrenceSeriesEndDate($newStartsAt, $validated, $repeatEvery, $repeatUnit);

        if ($repeatEndsOn->lt($newStartsAt->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'recurrence' => 'Dátum ukončenia opakovania nemôže byť pred prvým termínom.',
            ]);
        }

        $createdCount = 0;

        foreach ($this->buildRecurringOccurrenceDateTimes($newStartsAt, $newEndsAt, $repeatEndsOn, $validated, $repeatEvery, $repeatUnit) as [$cursorStart, $cursorEnd]) {
            if ($createdCount >= self::MAX_RECURRING_OCCURRENCES) {
                if ($hasExplicitRepeatEnd) {
                    throw ValidationException::withMessages([
                        'recurrence' => 'Opakovanie je príliš dlhé. Skráťte rozsah opakovania.',
                    ]);
                }

                break;
            }

            if ($disabledDayService->isDisabled($branch, $cursorStart)) {
                continue;
            }

            $this->createWindow($branch, $service, $cursorStart, $cursorEnd, $validated, $seriesUuid);

            $createdCount++;
        }

        if ($createdCount === 0) {
            throw ValidationException::withMessages([
                'recurrence' => 'V zadanom rozsahu opakovania nie je žiadny dostupný deň.',
            ]);
        }
    }

    private function buildRecurringOccurrenceDateTimes(
        Carbon $startsAt,
        Carbon $endsAt,
        Carbon $repeatEndsOn,
        array $validated,
        int $repeatEvery,
        string $repeatUnit,
    ): array {
        if (filled($validated['recurrence'] ?? null)) {
            $recurrenceService = app(RecurrenceService::class);

            return $recurrenceService
                ->getOccurrenceDates(
                    seriesStart: $startsAt->copy(),
                    rangeStart: $startsAt->copy(),
                    rangeEnd: $repeatEndsOn->copy(),
                    recurrence: $validated['recurrence'],
                )
                ->map(function (Carbon $occurrenceDate) use ($startsAt, $endsAt): array {
                    $occurrenceStart = $startsAt->copy()->setDate(
                        $occurrenceDate->year,
                        $occurrenceDate->month,
                        $occurrenceDate->day,
                    );

                    $occurrenceEnd = $endsAt->copy()->setDate(
                        $occurrenceDate->year,
                        $occurrenceDate->month,
                        $occurrenceDate->day,
                    );

                    return [$occurrenceStart, $occurrenceEnd];
                })
                ->values()
                ->all();
        }

        $cursorStart = $startsAt->copy();
        $cursorEnd = $endsAt->copy();
        $occurrences = [];

        while ($cursorStart->lte($repeatEndsOn)) {
            $occurrences[] = [$cursorStart->copy(), $cursorEnd->copy()];

            match ($repeatUnit) {
                'days' => $this->addDays($cursorStart, $cursorEnd, $repeatEvery),
                'months' => $this->addMonths($cursorStart, $cursorEnd, $repeatEvery),
                default => $this->addWeeks($cursorStart, $cursorEnd, $repeatEvery),
            };
        }

        return $occurrences;
    }

    private function resolveRecurrenceSeriesEndDate(
        Carbon $startsAt,
        array $validated,
        int $repeatEvery,
        string $repeatUnit,
    ): Carbon {
        $normalizedRecurrence = $validated['recurrence'] ?? null;
        $endsType = $normalizedRecurrence['ends']['type'] ?? 'never';

        if ($endsType === 'on' && filled($normalizedRecurrence['ends']['until'] ?? null)) {
            return Carbon::parse($normalizedRecurrence['ends']['until'])->endOfDay();
        }

        if ($endsType === 'after' && filled($normalizedRecurrence['ends']['count'] ?? null)) {
            $count = max(1, (int) $normalizedRecurrence['ends']['count']);
            $cursor = $startsAt->copy();

            for ($index = 1; $index < $count; $index++) {
                match ($repeatUnit) {
                    'days' => $cursor->addDays($repeatEvery),
                    'months' => $cursor->addMonths($repeatEvery),
                    default => $cursor->addWeeks($repeatEvery),
                };
            }

            return $cursor->endOfDay();
        }

        return $startsAt->copy()->addYears(2)->endOfDay();
    }

    public function reschedule(
        Request $request,
        Branch $branch,
        CapacityWindow $capacityWindow,
        CapacityWindowService $capacityWindowService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        $this->authorizeCapacityWindow($request, $branch, $capacityWindow);
        $disabledDayService = app(DisabledDayService::class);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reschedule_scope' => ['nullable', 'in:occurrence,from_date,series'],
            'from_date' => ['nullable', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $scope = $this->resolveSeriesScope(
            requestedScope: $validated['reschedule_scope'] ?? null,
            applyToSeries: null,
            capacityWindow: $capacityWindow,
        );

        $newStartsAt = Carbon::parse($validated['starts_at']);
        $newEndsAt = Carbon::parse($validated['ends_at']);
        $notifyPatient = $request->boolean('notify_patient', true);
        $reason = $validated['notification_reason'] ?? null;

        if ($disabledDayService->isDisabled($branch, $newStartsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Tento deň je v kalendári zakázaný.',
            ]);
        }

        if ($scope === 'occurrence') {
            DB::transaction(function () use (
                $capacityWindow,
                $capacityWindowService,
                $notificationService,
                $newStartsAt,
                $newEndsAt,
                $notifyPatient,
                $reason,
            ): void {
                $capacityWindowService->rescheduleWindow(
                    capacityWindow: $capacityWindow,
                    newStartsAt: $newStartsAt,
                    newEndsAt: $newEndsAt,
                    notifyPatient: $notifyPatient,
                    reason: $reason,
                    notificationService: $notificationService,
                );

                $capacityWindow->refresh();

                if (filled($capacityWindow->series_uuid)) {
                    $capacityWindow->update([
                        'series_uuid' => null,
                    ]);
                }
            });
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

        $windows = $this->getCapacityWindowsForScope(
            capacityWindow: $capacityWindow,
            scope: $deleteScope,
            fromDate: $validated['from_date'] ?? null,
            activeOnly: true,
        );

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
            action: $deleteScope === 'from_date'
                ? 'capacity_window_series_deleted_from_date'
                : 'capacity_window_series_deleted',
            capacityWindowId: $capacityWindow->id,
        );

        return back()->with(
            'success',
            $deleteScope === 'from_date'
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

        $windows = $this->getCapacityWindowsForScope(
            capacityWindow: $capacityWindow,
            scope: $scope,
            fromDate: $fromDate,
            activeOnly: true,
        );

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

    private function getCapacityWindowsForScope(
        CapacityWindow $capacityWindow,
        string $scope,
        ?string $fromDate,
        bool $activeOnly = true,
    ) {
        if ($scope === 'occurrence' || blank($capacityWindow->series_uuid)) {
            return CapacityWindow::query()
                ->where('branch_id', $capacityWindow->branch_id)
                ->whereKey($capacityWindow->id)
                ->when($activeOnly, fn ($query) => $query->where('status', 'active'))
                ->lockForUpdate()
                ->get();
        }

        $query = CapacityWindow::query()
            ->where('branch_id', $capacityWindow->branch_id)
            ->where('series_uuid', $capacityWindow->series_uuid)
            ->when($activeOnly, fn ($query) => $query->where('status', 'active'));

        if ($scope === 'from_date') {
            $query->where(
                'starts_at',
                '>=',
                Carbon::parse($fromDate ?? $capacityWindow->starts_at)->startOfDay(),
            );
        }

        return $query
            ->orderBy('starts_at')
            ->lockForUpdate()
            ->get();
    }

    private function resolveRepeatEvery(array $validated): int
    {
        if (filled($validated['recurrence'] ?? null)) {
            return (int) ($validated['recurrence']['interval'] ?? 1);
        }

        return max(1, (int) ($validated['repeat_every'] ?? 1));
    }

    private function resolveRepeatUnit(array $validated): string
    {
        if (filled($validated['recurrence'] ?? null)) {
            return match ($validated['recurrence']['frequency'] ?? 'weekly') {
                'daily' => 'days',
                'monthly' => 'months',
                'yearly' => 'months',
                default => 'weeks',
            };
        }

        return $validated['repeat_unit'] ?? 'weeks';
    }

    private function resolveRepeatEndsOn(array $validated): ?string
    {
        if (filled($validated['recurrence'] ?? null)) {
            $ends = $validated['recurrence']['ends'] ?? [];

            return ($ends['type'] ?? 'never') === 'on'
                ? ($ends['until'] ?? null)
                : null;
        }

        return $validated['repeat_ends_on'] ?? null;
    }

    private function hasExplicitRepeatEnd(array $validated): bool
    {
        if (filled($validated['repeat_ends_on'] ?? null)) {
            return true;
        }

        if (! filled($validated['recurrence'] ?? null)) {
            return false;
        }

        $ends = $validated['recurrence']['ends'] ?? [];
        $type = $ends['type'] ?? 'never';

        if ($type === 'on') {
            return filled($ends['until'] ?? null);
        }

        if ($type === 'after') {
            return filled($ends['count'] ?? null);
        }

        return false;
    }

    private function resolveSeriesScope(
        ?string $requestedScope,
        ?bool $applyToSeries,
        CapacityWindow $capacityWindow,
    ): string {
        $scope = $requestedScope;

        if (! $scope) {
            $scope = $applyToSeries ? 'series' : 'occurrence';
        }

        if ($scope !== 'occurrence' && blank($capacityWindow->series_uuid)) {
            return 'occurrence';
        }

        return $scope;
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
    ): CapacityWindow {
        return CapacityWindow::query()->create([
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