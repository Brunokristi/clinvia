<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateBookingAction;
use App\Actions\RescheduleBookingAction;
use App\Events\BranchCalendarUpdated;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Branch;
use App\Services\AdminBookingNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchBookingController extends Controller
{
    public function store(
        Request $request,
        Branch $branch,
        CreateBookingAction $createBookingAction,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

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
        ]);

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
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $booking->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

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
            'admin_note' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking->loadMissing(['services', 'capacityWindow']);

        $oldStartsAt = $booking->starts_at?->copy();
        $oldEndsAt = $booking->ends_at?->copy();

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
}