<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateBookingAction;
use App\Actions\RescheduleBookingAction;
use App\Events\BranchCalendarUpdated;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Branch;
use App\Services\AdminBookingNotificationService;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BranchBookingController extends Controller
{
    public function store(
        Request $request,
        Branch $branch,
        CreateBookingAction $createBookingAction,
        RecurrenceService $recurrenceService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $recurrenceRules = $request->input('recurrence');

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_note' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
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

        if ($recurrenceRules) {
            $validated['recurrence'] = $recurrenceService->normalize($recurrenceRules);
        }

        $createBookingAction->execute($branch, [
            ...$validated,
            'capacity_window_id' => null,
            'status' => 'confirmed',
            'notify_patient' => $request->boolean('notify_patient', true),
        ]);

        return back()->with('success', 'Rezervácia bola vytvorená.');
    }

    public function update(
        Request $request,
        Branch $branch,
        Booking $booking,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $booking->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,cancelled,completed,no_show'],
            'admin_note' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldStatus = $booking->status;

        $booking->update([
            'status' => $validated['status'],
            'admin_note' => $validated['admin_note'] ?? null,
        ]);

        BranchCalendarUpdated::dispatch(
            branchId: $booking->branch_id,
            action: $validated['status'] === 'cancelled'
                ? 'booking_cancelled'
                : 'booking_updated',
            bookingId: $booking->id,
        );

        if (
            $oldStatus !== 'cancelled'
            && $validated['status'] === 'cancelled'
            && $request->boolean('notify_patient', true)
        ) {
            $booking->refresh()->load(['branch', 'service', 'services', 'capacityWindow']);

            $notificationService->sendCancelledNotification(
                booking: $booking,
                reason: $validated['notification_reason'] ?? null,
            );
        }

        return back()->with('success', 'Rezervácia bola upravená.');
    }

    public function cancel(
        Request $request,
        Branch $branch,
        Booking $booking,
        CreateBookingAction $createBookingAction,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $booking->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
            'delete_scope' => ['nullable', 'in:occurrence,from_date,series'],
            'date' => ['nullable', 'date'],
        ]);

        $scope = $validated['delete_scope'] ?? null;

        if ($booking->recurrence && $scope) {
            $occurrenceDate = $this->resolveOccurrenceDate($booking, $validated['date'] ?? null);

            DB::transaction(function () use ($branch, $booking, $createBookingAction, $validated, $scope): void {
                if ($scope === 'occurrence') {
                    $this->excludeBookingOccurrence(
                        booking: $booking,
                        occurrenceDate: $this->resolveOccurrenceDate($booking, $validated['date'] ?? null),
                    );

                    return;
                }

                if ($scope === 'from_date') {
                    $this->endBookingSeriesFromDate(
                        booking: $booking,
                        occurrenceDate: $this->resolveOccurrenceDate($booking, $validated['date'] ?? null),
                        adminNote: $validated['admin_note'] ?? $booking->admin_note,
                    );

                    return;
                }

                $booking->update([
                    'status' => 'cancelled',
                    'admin_note' => $validated['admin_note'] ?? $booking->admin_note,
                ]);
            });

            BranchCalendarUpdated::dispatch(
                branchId: $booking->branch_id,
                action: 'booking_deleted',
                bookingId: $booking->id,
            );

            if ($request->boolean('notify_patient', true)) {
                $booking->refresh()->load(['branch', 'service', 'services', 'capacityWindow']);

                $cancelStartsAt = $scope === 'series'
                    ? $booking->starts_at?->copy()
                    : $this->withOccurrenceDate($booking->starts_at?->copy(), $occurrenceDate);

                $cancelEndsAt = $scope === 'series'
                    ? $booking->ends_at?->copy()
                    : $this->withOccurrenceDate($booking->ends_at?->copy(), $occurrenceDate);

                if ($booking->patient_email) {
                    Notification::route('mail', $booking->patient_email)
                        ->notify(new \App\Notifications\BookingCancelledNotification(
                            booking: $booking,
                            reason: $validated['notification_reason'] ?? null,
                            appointmentStartsAt: $cancelStartsAt,
                            appointmentEndsAt: $cancelEndsAt,
                        ));
                }
            }

            return back()->with('success', 'Rezervácia bola zrušená.');
        }

        $oldStatus = $booking->status;

        $booking->update([
            'status' => 'cancelled',
            'admin_note' => $validated['admin_note'] ?? $booking->admin_note,
        ]);

        BranchCalendarUpdated::dispatch(
            branchId: $booking->branch_id,
            action: 'booking_cancelled',
            bookingId: $booking->id,
        );

        if ($oldStatus !== 'cancelled' && $request->boolean('notify_patient', true)) {
            $booking->refresh()->load(['branch', 'service', 'services', 'capacityWindow']);

            $notificationService->sendCancelledNotification(
                booking: $booking,
                reason: $validated['notification_reason'] ?? null,
            );
        }

        return back()->with('success', 'Rezervácia bola zrušená.');
    }

    public function reschedule(
        Request $request,
        Branch $branch,
        Booking $booking,
        RescheduleBookingAction $rescheduleBookingAction,
        RecurrenceService $recurrenceService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $booking->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
            'reschedule_scope' => ['nullable', 'in:occurrence,from_date,series'],
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

        if (filled($validated['recurrence'] ?? null)) {
            $validated['recurrence'] = $recurrenceService->normalize($validated['recurrence']);
        }

        $recurrenceChanged = filled($validated['recurrence'] ?? null)
            && $recurrenceService->normalize($validated['recurrence']) !== $recurrenceService->normalize($booking->recurrence ?? []);

        $booking->loadMissing(['services', 'capacityWindow']);

        $oldStartsAt = $booking->starts_at?->copy();
        $oldEndsAt = $booking->ends_at?->copy();

        if ($booking->recurrence && filled($validated['reschedule_scope'] ?? null)) {
            $scope = $validated['reschedule_scope'];

            if ($recurrenceChanged && $scope === 'occurrence') {
                $scope = 'series';
                $validated['reschedule_scope'] = 'series';
            }

            if ($scope === 'occurrence') {
                $this->rescheduleRecurringOccurrence(
                    branch: $branch,
                    booking: $booking,
                    data: $validated,
                    createBookingAction: $rescheduleBookingAction instanceof CreateBookingAction ? $rescheduleBookingAction : app(CreateBookingAction::class),
                );

                BranchCalendarUpdated::dispatch(
                    branchId: $booking->branch_id,
                    action: 'booking_rescheduled',
                    bookingId: $booking->id,
                );

                return back()->with('success', 'Rezervácia bola presunutá.');
            }

            if ($scope === 'from_date') {
                $this->rescheduleRecurringSeriesFromDate(
                    branch: $branch,
                    booking: $booking,
                    data: $validated,
                    createBookingAction: app(CreateBookingAction::class),
                );

                BranchCalendarUpdated::dispatch(
                    branchId: $booking->branch_id,
                    action: 'booking_rescheduled',
                    bookingId: $booking->id,
                );

                return back()->with('success', 'Rezervácia bola presunutá.');
            }

            if ($scope === 'series') {
                $validated['reset_recurrence_excluded_dates'] = true;
            }
        }

        $rescheduledBooking = $rescheduleBookingAction->execute($branch, $booking, $validated);

        if ($request->boolean('notify_patient', true)) {
            $notificationService->sendRescheduledNotification(
                booking: $rescheduledBooking,
                oldStartsAt: $oldStartsAt,
                oldEndsAt: $oldEndsAt,
                reason: $validated['notification_reason'] ?? null,
            );
        }

        return back()->with('success', 'Rezervácia bola presunutá.');
    }

    public function duplicate(
        Request $request,
        Branch $branch,
        Booking $booking,
        CreateBookingAction $createBookingAction,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $booking->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_note' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
            'recurrence' => ['nullable', 'array'],
            'notify_patient' => ['nullable', 'boolean'],
        ]);

        $createBookingAction->execute($branch, [
            ...$validated,
            'capacity_window_id' => null,
            'status' => 'confirmed',
            'notify_patient' => false,
            'recurrence' => $validated['recurrence'] ?? $booking->recurrence,
        ]);

        BranchCalendarUpdated::dispatch(
            branchId: $booking->branch_id,
            action: 'booking_created',
        );

        return back()->with('success', 'Rezervácia bola duplikovaná.');
    }

    private function resolveOccurrenceDate(Booking $booking, ?string $date): Carbon
    {
        return Carbon::parse($date ?? $booking->starts_at)->startOfDay();
    }

    private function excludeBookingOccurrence(Booking $booking, Carbon $occurrenceDate): void
    {
        $booking->update([
            'recurrence_excluded_dates' => collect($booking->recurrence_excluded_dates ?? [])
                ->push($occurrenceDate->toDateString())
                ->filter()
                ->map(fn ($date): string => Carbon::parse($date)->toDateString())
                ->unique()
                ->sort()
                ->values()
                ->all(),
        ]);
    }

    private function endBookingSeriesFromDate(Booking $booking, Carbon $occurrenceDate, ?string $adminNote): void
    {
        $recurrence = $booking->recurrence ?? [];
        $recurrence['ends'] = [
            'type' => 'on',
            'count' => null,
            'until' => $occurrenceDate->copy()->subDay()->toDateString(),
        ];

        $booking->update([
            'recurrence' => $recurrence,
            'admin_note' => $adminNote ?? $booking->admin_note,
        ]);
    }

    private function rescheduleRecurringOccurrence(
        Branch $branch,
        Booking $booking,
        array $data,
        CreateBookingAction $createBookingAction,
    ): void {
        $occurrenceDate = $this->resolveOccurrenceDate($booking, $data['date'] ?? null);

        $createBookingAction->execute($branch, [
            'service_id' => $data['service_id'] ?? $booking->service_id,
            'service_ids' => $data['service_ids'] ?? $booking->services->pluck('id')->all(),
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? $booking->ends_at,
            'patient_name' => $booking->patient_name,
            'patient_email' => $booking->patient_email,
            'patient_phone' => $booking->patient_phone,
            'patient_note' => $booking->patient_note,
            'admin_note' => $data['admin_note'] ?? $booking->admin_note,
            'status' => 'confirmed',
            'notify_patient' => $data['notify_patient'] ?? false,
        ]);

        $this->excludeBookingOccurrence($booking, $occurrenceDate);
    }

    private function rescheduleRecurringSeriesFromDate(
        Branch $branch,
        Booking $booking,
        array $data,
        CreateBookingAction $createBookingAction,
    ): void {
        $occurrenceDate = $this->resolveOccurrenceDate($booking, $data['date'] ?? null);
        $seriesRecurrence = $booking->recurrence ?? [];
        $updatedRecurrence = $seriesRecurrence;

        $updatedRecurrence['ends'] = [
            'type' => 'on',
            'count' => null,
            'until' => $occurrenceDate->copy()->subDay()->toDateString(),
        ];

        $booking->update([
            'recurrence' => $updatedRecurrence,
            'admin_note' => $data['admin_note'] ?? $booking->admin_note,
        ]);

        $createBookingAction->execute($branch, [
            'service_id' => $data['service_id'] ?? $booking->service_id,
            'service_ids' => $data['service_ids'] ?? $booking->services->pluck('id')->all(),
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? $booking->ends_at,
            'patient_name' => $booking->patient_name,
            'patient_email' => $booking->patient_email,
            'patient_phone' => $booking->patient_phone,
            'patient_note' => $booking->patient_note,
            'admin_note' => $data['admin_note'] ?? $booking->admin_note,
            'status' => 'confirmed',
            'notify_patient' => $data['notify_patient'] ?? false,
            'recurrence' => $data['recurrence'] ?? $seriesRecurrence,
        ]);
    }

    private function withOccurrenceDate(?Carbon $dateTime, Carbon $occurrenceDate): ?Carbon
    {
        if (! $dateTime) {
            return null;
        }

        return $dateTime->copy()->setDate(
            $occurrenceDate->year,
            $occurrenceDate->month,
            $occurrenceDate->day,
        );
    }
}