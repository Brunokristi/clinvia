<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ConvertAppointmentRequestToBookingAction;
use App\Events\BranchCalendarUpdated;
use App\Http\Controllers\Controller;
use App\Models\AppointmentRequest;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchInboxMessage;
use App\Models\Service;
use App\Services\AdminBookingCalendarService;
use App\Services\CapacityWindowService;
use App\Notifications\RequestCancelledNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class BranchBookingCalendarController extends Controller
{
    public function index(
        Request $request,
        Branch $branch,
        AdminBookingCalendarService $calendarService,
        CapacityWindowService $capacityWindowService,
    ): Response {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $date = Carbon::parse($request->string('date', now()->toDateString()));
        $rangeStart = now()->copy()->subMonth()->startOfDay();
        $rangeEnd = now()->copy()->addMonths(6)->endOfDay();

       $branch->load([
            'company:id,legal_name,slug',
            'publicSite',
            'openingHours.intervals',
            'bookingAvailabilityRules.services',
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

            'availableRescheduleSlots' => [],

            'calendarBookings' => $calendarService->getCalendarBookings(
                branch: $branch,
                rangeStart: $rangeStart,
                rangeEnd: $rangeEnd,
            ),

            'calendarCapacityWindows' => $capacityWindowService->getCalendarCapacityWindows(
                branch: $branch,
                rangeStart: $rangeStart,
                rangeEnd: $rangeEnd,
            ),

            'pendingAppointmentRequests' => $this->getPendingAppointmentRequests($branch),

            'todayBookingsCount' => Booking::query()
                ->where('branch_id', $branch->id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->whereDate('starts_at', $date->toDateString())
                ->count(),

            'unreadMessagesCount' => BranchInboxMessage::query()
                ->where('branch_id', $branch->id)
                ->whereNull('read_at')
                ->count(),

            'selectedDate' => $date->toDateString(),
        ]);
    }

    public function dashboard(Request $request, Branch $branch): Response
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $today = now()->toDateString();

        $todayBookings = Booking::query()
            ->with(['service', 'services'])
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereDate('starts_at', $today)
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('Admin/Branches/Dashboard', [
            'branch' => $branch->load(['company:id,legal_name,slug']),

            'todayBookingsCount' => $todayBookings->count(),

            'todayAgenda' => $todayBookings->map(function (Booking $booking): array {
                $serviceName = $booking->services?->pluck('name')->filter()->join(', ')
                    ?: $booking->service?->name;

                return [
                    'id' => $booking->id,
                    'time' => $booking->starts_at
                        ? Carbon::parse($booking->starts_at)->format('H:i')
                        : '—',
                    'patient_name' => $booking->patient_name,
                    'patient_email' => $booking->patient_email,
                    'patient_phone' => $booking->patient_phone,
                    'service_name' => $serviceName,
                    'status' => $booking->status,
                    'status_label' => match ($booking->status) {
                        'confirmed' => 'Potvrdené',
                        'pending' => 'Čaká',
                        'completed' => 'Dokončené',
                        'no_show' => 'Neprišiel',
                        default => 'Neznáme',
                    },
                ];
            })->values(),

            'pendingAppointmentRequestsCount' => AppointmentRequest::query()
                ->where('branch_id', $branch->id)
                ->where('status', 'pending')
                ->count(),

            'unreadMessagesCount' => BranchInboxMessage::query()
                ->where('branch_id', $branch->id)
                ->whereNull('read_at')
                ->count(),

            'servicesCount' => Service::query()
                ->where('branch_id', $branch->id)
                ->count(),

            'employeesCount' => $branch->employees()->count(),
            'contactsCount' => $branch->contacts()->count(),
            'usersCount' => $branch->users()->count(),
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

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'booking_services_updated',
        );

        return back()->with('success', 'Nastavenia služieb boli uložené.');
    }

    public function convertAppointmentRequest(
        Request $request,
        Branch $branch,
        AppointmentRequest $appointmentRequest,
        ConvertAppointmentRequestToBookingAction $convertAppointmentRequestToBookingAction,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $appointmentRequest->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
        ]);

        $convertAppointmentRequestToBookingAction->execute($branch, $appointmentRequest, [
            ...$validated,
            'notify_patient' => $request->boolean('notify_patient', true),
        ]);

        return back()->with('success', 'Žiadosť bola presunutá do kalendára.');
    }

    public function cancelAppointmentRequest(
        Request $request,
        Branch $branch,
        AppointmentRequest $appointmentRequest,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $appointmentRequest->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $appointmentRequest->update([
            'status' => 'cancelled',
        ]);

        if ($request->boolean('notify_patient', true) && $appointmentRequest->patient_email) {
            Notification::route('mail', $appointmentRequest->patient_email)
                ->notify(new RequestCancelledNotification(
                    appointmentRequest: $appointmentRequest,
                    reason: $validated['notification_reason'] ?? null,
                ));
        }

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'appointment_request_cancelled',
            appointmentRequestId: $appointmentRequest->id,
        );

        return back()->with('success', 'Žiadosť bola zrušená.');
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
            ->map(fn (AppointmentRequest $appointmentRequest): array => [
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
                    ->map(fn (Service $service): array => [
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