<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Service;
use App\Services\AdminBookingCalendarService;
use App\Services\AdminBookingNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchBookingController extends Controller
{
    public function store(
        Request $request,
        Branch $branch,
        AdminBookingCalendarService $calendarService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $validated = $request->validate([
            'booking_slot_id' => ['nullable', 'integer', 'exists:booking_slots,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_note' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
        ]);

        $slot = $calendarService->resolveSlotForAdminBooking($branch, $validated);

        $booking = Booking::create([
            'booking_slot_id' => $slot->id,
            'branch_id' => $branch->id,
            'service_id' => $slot->service_id,
            'patient_name' => $validated['patient_name'],
            'patient_email' => $validated['patient_email'] ?? null,
            'patient_phone' => $validated['patient_phone'] ?? null,
            'patient_note' => $validated['patient_note'] ?? null,
            'admin_note' => $validated['admin_note'] ?? null,
            'status' => 'confirmed',
        ]);

        if (($validated['notify_patient'] ?? false) && filled($booking->patient_email)) {
            $notificationService->sendCreatedNotification($booking);
        }

        return back()->with('success', 'Rezervácia bola vytvorená.');
    }

    public function update(
        Request $request,
        Branch $branch,
        Booking $booking,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($booking->branch_id !== $branch->id, 404);

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

        if (
            $oldStatus !== 'cancelled'
            && $validated['status'] === 'cancelled'
            && ($validated['notify_patient'] ?? true)
        ) {
            $notificationService->sendCancelledNotification($booking, $validated['notification_reason'] ?? null);
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
        abort_if($booking->branch_id !== $branch->id, 404);

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking->update([
            'status' => 'cancelled',
            'admin_note' => $validated['admin_note'] ?? $booking->admin_note,
        ]);

        if ($validated['notify_patient'] ?? true) {
            $notificationService->sendCancelledNotification($booking, $validated['notification_reason'] ?? null);
        }

        return back()->with('success', 'Rezervácia bola zrušená.');
    }

    public function reschedule(
        Request $request,
        Branch $branch,
        Booking $booking,
        AdminBookingCalendarService $calendarService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($booking->branch_id !== $branch->id, 404);

        $validated = $request->validate([
            'booking_slot_id' => ['nullable', 'integer', 'exists:booking_slots,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'starts_at' => ['nullable', 'date'],
            'admin_note' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldSlot = $booking->bookingSlot;
        $oldStartsAt = $oldSlot?->starts_at?->copy();
        $oldEndsAt = $oldSlot?->ends_at?->copy();
        $serviceId = $validated['service_id'] ?? $booking->service_id;

        if (! empty($validated['booking_slot_id'])) {
            $newSlot = $calendarService->resolveSlotForAdminBooking($branch, [
                ...$validated,
                'service_id' => $serviceId,
            ]);
        } else {
            if (empty($validated['starts_at'])) {
                return back()->withErrors([
                    'starts_at' => 'Vyberte nový začiatok rezervácie.',
                ]);
            }

            $service = Service::query()
                ->where('branch_id', $branch->id)
                ->whereKey($serviceId)
                ->firstOrFail();

            $durationMinutes = (int) (
                $service->duration_minutes
                ?? $service->duration
                ?? $service->length_minutes
                ?? $service->minutes
                ?? 0
            );

            if ($durationMinutes <= 0) {
                return back()->withErrors([
                    'service_id' => 'Vybraná služba nemá nastavené trvanie.',
                ]);
            }

            $startsAt = Carbon::parse($validated['starts_at']);
            $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

            $newSlot = $calendarService->resolveSlotForAdminBooking($branch, [
                'service_id' => $service->id,
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $endsAt->toDateTimeString(),
            ]);
        }

        $booking->update([
            'booking_slot_id' => $newSlot->id,
            'service_id' => $newSlot->service_id,
            'status' => 'confirmed',
            'admin_note' => $validated['admin_note'] ?? $booking->admin_note,
        ]);

        if ($validated['notify_patient'] ?? false) {
            $booking->refresh()->load(['branch', 'service', 'bookingSlot']);

            $notificationService->sendRescheduledNotification(
                booking: $booking,
                oldStartsAt: $oldStartsAt,
                oldEndsAt: $oldEndsAt,
                reason: $validated['notification_reason'] ?? null,
            );
        }

        return back()->with('success', 'Rezervácia bola presunutá.');
    }
}
