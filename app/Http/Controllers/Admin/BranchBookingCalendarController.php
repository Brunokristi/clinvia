<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ConvertAppointmentRequestToBookingAction;
use App\Events\BranchCalendarUpdated;
use App\Http\Controllers\Controller;
use App\Models\AppointmentRequest;
use App\Models\Booking;
use App\Models\BookingAvailabilityRule;
use App\Models\Branch;
use App\Models\BranchDisabledDay;
use App\Models\BranchInboxMessage;
use App\Models\Service;
use App\Notifications\RequestCancelledNotification;
use App\Services\AdminBookingCalendarService;
use App\Services\CapacityWindowService;
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
        AdminBookingCalendarService $calendarService,
        CapacityWindowService $capacityWindowService,
    ): JsonResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        $rangeStart = Carbon::parse($validated['start'])->startOfDay();
        $rangeEnd = Carbon::parse($validated['end'])->endOfDay();

        $availabilityRules = BookingAvailabilityRule::query()
            ->where('branch_id', $branch->id)
            ->with('services')
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (BookingAvailabilityRule $rule): array => $this->formatAvailabilityRuleForFrontend($rule))
            ->values();

        $calendarBookings = $calendarService->getCalendarBookings(
            branch: $branch,
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
        );

        $calendarCapacityWindows = $capacityWindowService->getCalendarCapacityWindows(
            branch: $branch,
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
        );

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
        AdminBookingCalendarService $calendarService,
        CapacityWindowService $capacityWindowService,
        PatientDirectoryService $patientDirectoryService,
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

        $availabilityRules = BookingAvailabilityRule::query()
            ->where('branch_id', $branch->id)
            ->with('services')
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (BookingAvailabilityRule $rule): array => $this->formatAvailabilityRuleForFrontend($rule))
            ->values();

        $calendarBookings = $calendarService->getCalendarBookings(
            branch: $branch,
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
        );

        $calendarCapacityWindows = $capacityWindowService->getCalendarCapacityWindows(
            branch: $branch,
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
        );

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

            'todayBookingsCount' => $calendarService->getCalendarBookings(
                branch: $branch,
                rangeStart: $date->copy()->startOfDay(),
                rangeEnd: $date->copy()->endOfDay(),
            )->count(),

            'unreadMessagesCount' => BranchInboxMessage::query()
                ->where('branch_id', $branch->id)
                ->whereNull('read_at')
                ->count(),

            'selectedDate' => $date->toDateString(),
        ]);
    }

    public function dashboard(Request $request, Branch $branch, AdminBookingCalendarService $calendarService): Response
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $today = now()->toDateString();

        $todayBookings = $calendarService->getCalendarBookings(
            branch: $branch,
            rangeStart: Carbon::parse($today)->startOfDay(),
            rangeEnd: Carbon::parse($today)->endOfDay(),
        );

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

    private function formatAvailabilityRuleForFrontend(BookingAvailabilityRule $rule): array
    {
        $serviceIds = $rule->services
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($serviceIds === []) {
            $serviceIds = $this->normalizeServiceIds($rule->service_ids ?? []);
        }

        return [
            'id' => $rule->id,

            'date' => $rule->date
                ? Carbon::parse($rule->date)->toDateString()
                : null,

            'day_of_week' => $rule->day_of_week,

            'starts_at' => $this->formatTimeForFrontend($rule->starts_at),
            'ends_at' => $this->formatTimeForFrontend($rule->ends_at),

            'slot_mode' => $rule->slot_mode,
            'bookable_places' => (int) ($rule->bookable_places ?: 1),

            'service_id' => $rule->service_id ? (int) $rule->service_id : null,
            'service_ids' => $serviceIds,

            'services' => $rule->services
                ->map(fn (Service $service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'booking_type' => $service->booking_type,
                    'public_booking_type' => $service->public_booking_type,
                ])
                ->values()
                ->all(),

            'repeats' => (bool) $rule->repeats,
            'repeat_every' => (int) ($rule->repeat_every ?: 1),
            'repeat_unit' => $rule->repeat_unit ?: 'weeks',
            'repeat_weekdays' => collect($rule->repeat_weekdays ?? [])
                ->map(fn ($weekday): string => strtoupper((string) $weekday))
                ->filter(fn (string $weekday): bool => in_array($weekday, ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'], true))
                ->values()
                ->all(),

            'repeat_ends_on' => $rule->repeat_ends_on
                ? Carbon::parse($rule->repeat_ends_on)->toDateString()
                : null,

            'excluded_dates' => $this->normalizeDateStrings($rule->excluded_dates ?? []),

            'is_enabled' => (bool) $rule->is_enabled,
        ];
    }

    private function formatTimeForFrontend($time): ?string
    {
        if (! $time) {
            return null;
        }

        if ($time instanceof Carbon) {
            return $time->format('H:i');
        }

        $time = (string) $time;

        if (preg_match('/^\d{2}:\d{2}/', $time)) {
            return substr($time, 0, 5);
        }

        return Carbon::parse($time)->format('H:i');
    }

    private function normalizeServiceIds(array $serviceIds): array
    {
        return collect($serviceIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeDateStrings(array $dates): array
    {
        return collect($dates)
            ->filter()
            ->map(fn ($date): string => Carbon::parse($date)->startOfDay()->toDateString())
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}