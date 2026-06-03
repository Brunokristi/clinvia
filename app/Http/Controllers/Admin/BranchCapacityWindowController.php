<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAvailabilityRule;
use App\Models\Branch;
use App\Models\Service;
use App\Services\AdminBookingCalendarService;
use App\Services\AdminBookingNotificationService;
use App\Services\BookingSlotGenerator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BranchCapacityWindowController extends Controller
{
    public function cancel(
        Request $request,
        Branch $branch,
        BookingAvailabilityRule $rule,
        AdminBookingCalendarService $calendarService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $this->cancelBookings(
            bookings: $calendarService->getCapacityWindowBookingsForDate($branch, $rule, $date),
            notifyPatient: $validated['notify_patient'] ?? true,
            reason: $validated['notification_reason'] ?? null,
            notificationService: $notificationService,
        );

        $this->excludeRuleDate($rule, $date);

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleDate($rule, $date);
        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Kapacitné okno bolo zrušené.');
    }

    public function reschedule(
        Request $request,
        Branch $branch,
        BookingAvailabilityRule $rule,
        AdminBookingCalendarService $calendarService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldDate = Carbon::parse($validated['date'])->startOfDay();
        $newStartsAt = Carbon::parse($validated['starts_at']);
        $newEndsAt = Carbon::parse($validated['ends_at']);

        $bookings = $calendarService->getCapacityWindowBookingsForDate($branch, $rule, $oldDate);
        $service = $rule->services->first();

        if (! $service && $rule->service_id) {
            $service = Service::query()
                ->where('branch_id', $branch->id)
                ->whereKey($rule->service_id)
                ->first();
        }

        if (! $service) {
            throw ValidationException::withMessages([
                'service_id' => 'Služba pre toto kapacitné okno neexistuje.',
            ]);
        }

        $targetRule = $calendarService->moveCapacityWindowOccurrence(
            branch: $branch,
            rule: $rule,
            oldDate: $oldDate,
            newStartsAt: $newStartsAt,
            newEndsAt: $newEndsAt,
            serviceId: (int) $service->id,
        );

        $newSlot = $calendarService->createOrEnableCapacitySlot(
            branch: $branch,
            service: $service,
            startsAt: $newStartsAt,
            endsAt: $newEndsAt,
            capacity: max(1, (int) ($targetRule->bookable_places ?? $service->capacity ?? 1)),
        );

        foreach ($bookings as $booking) {
            $oldSlot = $booking->bookingSlot;
            $oldStartsAt = $oldSlot?->starts_at?->copy();
            $oldEndsAt = $oldSlot?->ends_at?->copy();

            $booking->update([
                'booking_slot_id' => $newSlot->id,
                'service_id' => $service->id,
                'status' => 'confirmed',
            ]);

            if ($validated['notify_patient'] ?? true) {
                $booking->refresh()->load(['branch', 'service', 'bookingSlot']);

                $notificationService->sendRescheduledNotification(
                    booking: $booking,
                    oldStartsAt: $oldStartsAt,
                    oldEndsAt: $oldEndsAt,
                    reason: $validated['notification_reason'] ?? null,
                );
            }
        }

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleDate($rule, $oldDate);
        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Kapacitné okno bolo presunuté.');
    }

    public function deleteOccurrence(
        Request $request,
        Branch $branch,
        BookingAvailabilityRule $rule,
        AdminBookingCalendarService $calendarService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $this->cancelBookings(
            bookings: $calendarService->getCapacityWindowBookingsForDate($branch, $rule, $date),
            notifyPatient: $validated['notify_patient'] ?? true,
            reason: $validated['notification_reason'] ?? null,
            notificationService: $notificationService,
        );

        $this->excludeRuleDate($rule, $date);

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleDate($rule, $date);
        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Tento skupinový termín bol vymazaný.');
    }

    public function deleteFutureOccurrences(
        Request $request,
        Branch $branch,
        BookingAvailabilityRule $rule,
        AdminBookingCalendarService $calendarService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $this->cancelBookings(
            bookings: $calendarService->getCapacityWindowBookingsFromDate($branch, $rule, $date),
            notifyPatient: $validated['notify_patient'] ?? true,
            reason: $validated['notification_reason'] ?? null,
            notificationService: $notificationService,
        );

        $rule->update([
            'repeat_ends_on' => $date->copy()->subDay()->toDateString(),
        ]);

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleFromDate($rule, $date);
        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Budúce skupinové termíny boli vymazané.');
    }

    public function deleteSeries(
        Request $request,
        Branch $branch,
        BookingAvailabilityRule $rule,
        AdminBookingCalendarService $calendarService,
        AdminBookingNotificationService $notificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = isset($validated['date'])
            ? Carbon::parse($validated['date'])->startOfDay()
            : now()->startOfDay();

        $this->cancelBookings(
            bookings: $calendarService->getCapacityWindowBookingsFromDate($branch, $rule, $date),
            notifyPatient: $validated['notify_patient'] ?? true,
            reason: $validated['notification_reason'] ?? null,
            notificationService: $notificationService,
        );

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleFromDate($rule, $date);

        $rule->delete();

        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Celá skupinová séria bola vymazaná.');
    }

    private function cancelBookings(
        iterable $bookings,
        bool $notifyPatient,
        ?string $reason,
        AdminBookingNotificationService $notificationService,
    ): void {
        foreach ($bookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
            ]);

            if ($notifyPatient) {
                $notificationService->sendCancelledNotification($booking, $reason);
            }
        }
    }

    private function excludeRuleDate(BookingAvailabilityRule $rule, Carbon $date): void
    {
        $excludedDates = $rule->excluded_dates ?? [];
        $dateString = $date->toDateString();

        if (! in_array($dateString, $excludedDates, true)) {
            $excludedDates[] = $dateString;
        }

        sort($excludedDates);

        $rule->update([
            'excluded_dates' => $excludedDates,
        ]);
    }
}
