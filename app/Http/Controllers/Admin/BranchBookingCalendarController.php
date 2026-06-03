<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchInboxMessage;
use App\Models\Service;
use App\Services\AdminBookingCalendarService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\AppointmentRequest;
use App\Models\BookingSlot;
use Illuminate\Validation\ValidationException;

class BranchBookingCalendarController extends Controller
{
    public function index(Request $request, Branch $branch, AdminBookingCalendarService $calendarService): Response
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $date = Carbon::parse($request->string('date', now()->toDateString()));
        $rangeStart = now()->copy()->subMonth()->startOfDay();
        $rangeEnd = now()->copy()->addMonths(6)->endOfDay();

        $branch->load([
            'company:id,legal_name,slug',
            'publicSite',
            'openingHours.intervals',
            'bookingAvailabilityRules.services',
            'bookingSlots.service',
            'branchInboxMessages' => function ($query) {
                $query->latest()->limit(15);
            },
        ]);

        return Inertia::render('Admin/Branches/Bookings', [
            'branch' => $branch,
            'services' => Service::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'availableRescheduleSlots' => $calendarService->getAvailableAdminSlots($branch),
            'calendarBookings' => $calendarService->getCalendarBookings($branch, $rangeStart, $rangeEnd),
            'calendarCapacityWindows' => $calendarService->getCalendarCapacityWindows($branch, $rangeStart, $rangeEnd),
            'pendingAppointmentRequests' => $this->getPendingAppointmentRequests($branch),
            'todayBookingsCount' => Booking::query()
                ->where('branch_id', $branch->id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->whereHas('bookingSlot', function ($query) use ($date) {
                    $query->whereDate('starts_at', $date);
                })
                ->count(),
            'unreadMessagesCount' => BranchInboxMessage::query()
                ->where('branch_id', $branch->id)
                ->whereNull('read_at')
                ->count(),
            'selectedDate' => $date->toDateString(),
        ]);
    }

    public function updateServices(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $validated = $request->validate([
            'services' => ['required', 'array'],
            'services.*.id' => ['required', 'integer', 'exists:services,id'],
            'services.*.is_bookable' => ['required', 'boolean'],
            'services.*.duration_minutes' => ['nullable', 'integer', 'min:1'],
            'services.*.capacity' => ['nullable', 'integer', 'min:1'],
            'services.*.buffer_before_minutes' => ['nullable', 'integer', 'min:0'],
            'services.*.buffer_after_minutes' => ['nullable', 'integer', 'min:0'],
            'services.*.booking_type' => ['required', 'in:individual,group'],
            'services.*.public_booking_type' => ['nullable', 'in:appointment_request,immediate_booking'],
        ]);

        DB::transaction(function () use ($branch, $validated): void {
            foreach ($validated['services'] as $item) {
                $service = Service::query()
                    ->where('branch_id', $branch->id)
                    ->whereKey($item['id'])
                    ->firstOrFail();

                $service->update([
                    'is_bookable' => $item['is_bookable'],
                    'duration_minutes' => $item['duration_minutes'] ?? null,
                    'capacity' => $item['capacity'] ?? 1,
                    'buffer_before_minutes' => $item['buffer_before_minutes'] ?? 0,
                    'buffer_after_minutes' => $item['buffer_after_minutes'] ?? 0,
                    'booking_type' => $item['booking_type'],
                    'public_booking_type' => $item['public_booking_type']
                        ?? $service->public_booking_type
                        ?? 'appointment_request',
                ]);
            }
        });

        return back()->with('success', 'Nastavenia služieb boli uložené.');
    }

    public function markMessageRead(Request $request, Branch $branch, BranchInboxMessage $message): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($message->branch_id !== $branch->id, 404);

        $message->update([
            'read_at' => now(),
        ]);

        return back();
    }

    private function getPendingAppointmentRequests(Branch $branch)
    {
        return AppointmentRequest::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'pending')
            ->with('services')
            ->orderBy('preferred_date')
            ->orderBy('created_at')
            ->get()
            ->map(fn (AppointmentRequest $appointmentRequest) => [
                'id' => $appointmentRequest->id,
                'preferred_date' => optional($appointmentRequest->preferred_date)->toDateString()
                    ?? (string) $appointmentRequest->preferred_date,
                'preferred_period' => $appointmentRequest->preferred_period,
                'total_duration_minutes' => $appointmentRequest->total_duration_minutes,
                'patient_name' => $appointmentRequest->patient_name,
                'patient_email' => $appointmentRequest->patient_email,
                'patient_phone' => $appointmentRequest->patient_phone,
                'patient_note' => $appointmentRequest->patient_note,
                'services' => $appointmentRequest->services
                    ->map(fn (Service $service) => [
                        'id' => $service->id,
                        'name' => $service->name,
                    ])
                    ->values(),
            ])
            ->values();
    }

    public function convertAppointmentRequest(
        Request $request,
        Branch $branch,
        AppointmentRequest $appointmentRequest
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $appointmentRequest->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
        ]);

        if ($appointmentRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'appointment_request_id' => 'Táto žiadosť už nie je čakajúca.',
            ]);
        }

        DB::transaction(function () use ($validated, $branch, $appointmentRequest): void {
            $appointmentRequest->loadMissing('services');

            $primaryService = $appointmentRequest->services->first();

            if (! $primaryService) {
                throw ValidationException::withMessages([
                    'service_id' => 'Žiadosť nemá vybranú službu.',
                ]);
            }

            $startsAt = Carbon::parse($validated['starts_at']);

            $durationMinutes = max(
                15,
                (int) $appointmentRequest->total_duration_minutes
            );

            $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

            $bookingSlot = BookingSlot::query()
            ->firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'service_id' => $primaryService->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ],
                [
                    'capacity' => 1,
                    'is_enabled' => true,
                ],
            );

            $booking = Booking::create([
                'branch_id' => $branch->id,
                'booking_slot_id' => $bookingSlot->id,
                'service_id' => $primaryService->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'patient_name' => $appointmentRequest->patient_name,
                'patient_email' => $appointmentRequest->patient_email,
                'patient_phone' => $appointmentRequest->patient_phone,
                'patient_note' => $appointmentRequest->patient_note,
                'status' => 'confirmed',
            ]);

            $appointmentRequest->update([
                'status' => 'converted',
                'booking_id' => $booking->id,
            ]);
        });

        return back()->with('success', 'Žiadosť bola presunutá do kalendára.');
    }
}
