<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppointmentRequest;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Branch;
use App\Models\BranchInboxMessage;
use App\Models\Service;
use App\Services\AdminBookingCalendarService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Actions\CreateBookingAction;

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

    public function convertAppointmentRequest(
        Request $request,
        Branch $branch,
        AppointmentRequest $appointmentRequest,
        CreateBookingAction $createBookingAction,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $appointmentRequest->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($validated, $branch, $appointmentRequest, $createBookingAction): void {
            $lockedAppointmentRequest = AppointmentRequest::query()
                ->whereKey($appointmentRequest->id)
                ->where('branch_id', $branch->id)
                ->with('services')
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAppointmentRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'appointment_request_id' => 'Táto žiadosť už nie je čakajúca.',
                ]);
            }

            $services = $lockedAppointmentRequest->services;

            if ($services->isEmpty()) {
                throw ValidationException::withMessages([
                    'service_ids' => 'Žiadosť nemá vybrané služby.',
                ]);
            }

            $primaryService = $services->first();

            $startsAt = Carbon::parse($validated['starts_at']);

            $durationMinutes = max(
                15,
                (int) $lockedAppointmentRequest->total_duration_minutes
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

            $booking = $createBookingAction->execute($branch, $bookingSlot, [
                'service_id' => $primaryService->id,
                'service_ids' => $services
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
                'patient_name' => $lockedAppointmentRequest->patient_name,
                'patient_email' => $lockedAppointmentRequest->patient_email,
                'patient_phone' => $lockedAppointmentRequest->patient_phone,
                'patient_note' => $lockedAppointmentRequest->patient_note,
                'notify_patient' => true,
            ]);

            $lockedAppointmentRequest->update([
                'status' => 'converted',
                'booking_id' => $booking->id,
            ]);
        });

        return back()->with('success', 'Žiadosť bola presunutá do kalendára.');
    }

    private function getPendingAppointmentRequests(Branch $branch)
    {
        return AppointmentRequest::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'pending')
            ->with('services')
            ->orderByRaw('preferred_date is null')
            ->orderBy('preferred_date')
            ->orderBy('created_at')
            ->get()
            ->map(fn (AppointmentRequest $appointmentRequest) => [
                'id' => $appointmentRequest->id,
                'request_type' => $appointmentRequest->request_type ?? 'preferred_period',
                'preferred_date' => $appointmentRequest->preferred_date?->toDateString(),
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
                        'duration_minutes' => $service->duration_minutes,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values();
    }
}