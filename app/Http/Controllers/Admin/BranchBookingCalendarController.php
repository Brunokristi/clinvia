<?php

namespace App\Http\Controllers\Admin;

use App\Events\BranchCalendarUpdated;
use App\Http\Controllers\Controller;
use App\Models\AppointmentRequest;
use App\Models\Branch;
use App\Models\BranchDisabledDay;
use App\Models\BranchInboxMessage;
use App\Models\Service;
use App\Notifications\RequestCancelledNotification;
use App\Modules\Calendar\Actions\ConvertAppointmentRequestToEventAction;
use App\Modules\Calendar\Services\EventReadAdapterService;
use App\Services\PatientDirectoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class BranchBookingCalendarController extends Controller
{
    public function events(
        Request $request,
        Branch $branch,
        EventReadAdapterService $eventReadAdapterService,
    ): JsonResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        $rangeStart = Carbon::parse($validated['start'])->startOfDay();
        $rangeEnd = Carbon::parse($validated['end'])->endOfDay();

        $legacyPayload = $eventReadAdapterService->getLegacyCalendarPayload(
            branch: $branch,
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
        );

        $availabilityRules = collect($legacyPayload['availabilityRules'] ?? []);
        $calendarBookings = collect($legacyPayload['calendarBookings'] ?? []);
        $calendarCapacityWindows = collect($legacyPayload['calendarCapacityWindows'] ?? []);

        $disabledDays = Schema::hasTable('branch_disabled_days')
            ? BranchDisabledDay::query()
                ->where('branch_id', $branch->id)
                ->whereBetween('date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                ->orderBy('date')
                ->get()
            : collect();

        return response()->json([
            'data' => [
                'availabilityRules' => $availabilityRules,
                'calendarBookings' => $calendarBookings,
                'calendarCapacityWindows' => $calendarCapacityWindows,
                'disabledDays' => $disabledDays,
            ],
        ]);
    }

    public function index(
        Request $request,
        Branch $branch,
        PatientDirectoryService $patientDirectoryService,
        EventReadAdapterService $eventReadAdapterService,
    ): Response {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $date = Carbon::parse($request->string('date', now()->toDateString()));
        $rangeStart = $request->filled('start')
            ? Carbon::parse($request->string('start'))->startOfDay()
            : now()->copy()->subMonth()->startOfDay();

        $rangeEnd = $request->filled('end')
            ? Carbon::parse($request->string('end'))->endOfDay()
            : now()->copy()->addMonths(6)->endOfDay();

        $branch->load([
            'company:id,legal_name,slug',
            'publicSite',
            'openingHours.intervals',
            'branchInboxMessages' => function ($query) {
                $query->latest()->limit(15);
            },
        ]);

        $services = Service::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $legacyPayload = $eventReadAdapterService->getLegacyCalendarPayload(
            branch: $branch,
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
        );

        $availabilityRules = collect($legacyPayload['availabilityRules'] ?? []);
        $calendarBookings = collect($legacyPayload['calendarBookings'] ?? []);
        $calendarCapacityWindows = collect($legacyPayload['calendarCapacityWindows'] ?? []);

        return Inertia::render('Admin/Branches/Bookings', [
            'branch' => $branch,

            'services' => $services,

            'patients' => $patientDirectoryService->getBranchPatientsForAutocomplete($branch),

            'availabilityRules' => $availabilityRules,

            'availableRescheduleSlots' => [],

            'calendarBookings' => $calendarBookings,
            
            'calendarCapacityWindows' => $calendarCapacityWindows,

            'disabledDays' => Schema::hasTable('branch_disabled_days')
                ? BranchDisabledDay::query()
                    ->where('branch_id', $branch->id)
                    ->whereBetween('date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                    ->orderBy('date')
                    ->get()
                : collect(),

            'pendingAppointmentRequests' => $this->getPendingAppointmentRequests($branch),

            'todayBookingsCount' => collect($eventReadAdapterService->getLegacyCalendarPayload(
                branch: $branch,
                rangeStart: $date->copy()->startOfDay(),
                rangeEnd: $date->copy()->endOfDay(),
            )['calendarBookings'] ?? [])->count(),

            'unreadMessagesCount' => BranchInboxMessage::query()
                ->where('branch_id', $branch->id)
                ->whereNull('read_at')
                ->count(),

            'selectedDate' => $date->toDateString(),
        ]);
    }

    public function dashboard(
        Request $request,
        Branch $branch,
        EventReadAdapterService $eventReadAdapterService,
    ): Response
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $today = now()->toDateString();

        $legacyPayload = $eventReadAdapterService->getLegacyCalendarPayload(
            branch: $branch,
            rangeStart: Carbon::parse($today)->startOfDay(),
            rangeEnd: Carbon::parse($today)->endOfDay(),
        );

        $todayBookings = collect($legacyPayload['calendarBookings'] ?? []);

        return Inertia::render('Admin/Branches/Dashboard', [
            'branch' => $branch->load(['company:id,legal_name,slug']),

            'todayBookingsCount' => $todayBookings->count(),

            'todayAgenda' => $todayBookings->map(function (array $booking): array {
                $serviceName = $booking['service_name'] ?? '—';

                return [
                    'id' => $booking['id'],
                    'time' => $booking['starts_at']
                        ? Carbon::parse($booking['starts_at'])->format('H:i')
                        : '—',
                    'patient_name' => $booking['patient_name'],
                    'patient_email' => $booking['patient_email'],
                    'patient_phone' => $booking['patient_phone'],
                    'service_name' => $serviceName,
                    'status' => $booking['status'],
                    'status_label' => match ($booking['status']) {
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
        ConvertAppointmentRequestToEventAction $convertAppointmentRequestToEventAction,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $appointmentRequest->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
            'selected_patient' => ['nullable', 'array'],
            'selected_patient.patient_name' => ['nullable', 'string', 'max:255'],
            'selected_patient.patient_email' => ['nullable', 'email', 'max:255'],
            'selected_patient.patient_phone' => ['nullable', 'string', 'max:255'],
            'selected_patient.patient_birth_number' => ['nullable', 'string', 'max:255'],
        ]);

        $startsAt = Carbon::parse($validated['starts_at']);

        $event = $convertAppointmentRequestToEventAction->execute($branch, $appointmentRequest, [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes((int) max(15, $appointmentRequest->total_duration_minutes)),
            'patient_name' => data_get($validated, 'selected_patient.patient_name') ?: $appointmentRequest->patient_name,
            'patient_email' => data_get($validated, 'selected_patient.patient_email') ?: $appointmentRequest->patient_email,
            'patient_phone' => data_get($validated, 'selected_patient.patient_phone') ?: $appointmentRequest->patient_phone,
            'patient_birth_number' => data_get($validated, 'selected_patient.patient_birth_number') ?: $appointmentRequest->patient_birth_number,
            'notify_patient' => $request->boolean('notify_patient', true),
        ], $request->user()?->id);

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'appointment_request_converted',
            bookingId: $event->id,
            appointmentRequestId: $appointmentRequest->id,
        );

        return back()->with('success', 'Ziadost bola presunuta do kalendara.');
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
                'patient_birth_number' => $appointmentRequest->patient_birth_number,
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