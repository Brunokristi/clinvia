<?php

namespace App\Http\Controllers\Admin;

use App\Events\BranchCalendarUpdated;
use App\Models\AppointmentRequestAuditLog;
use App\Http\Controllers\Controller;
use App\Models\AppointmentRequest;
use App\Models\Branch;
use App\Models\BranchInboxMessage;
use App\Models\Patient;
use App\Models\Service;
use App\Modules\Calendar\Actions\ConvertAppointmentRequestToEventAction;
use App\Modules\Calendar\Services\EventReadAdapterService;
use App\Modules\Calendar\Services\RecurringImpactService;
use App\Services\PatientMatchingService;
use App\Services\DisabledDayService;
use App\Services\EmailNotificationService;
use App\Services\PatientDirectoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BranchBookingCalendarController extends Controller
{
    public function recurringImpactPreview(
        Request $request,
        Branch $branch,
        RecurringImpactService $recurringImpactService,
    ): JsonResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $validated = $request->validate([
            'action' => ['required', 'in:delete,edit,reschedule,resize,change_recurrence'],
            'scope' => ['required', 'in:this,this_and_following,all,occurrence,from_date,series'],
            'changes' => ['nullable', 'array'],
            'selected_occurrence' => ['required', 'array'],
            'selected_occurrence.event_id' => ['required', 'integer'],
            'selected_occurrence.root_event_id' => ['nullable', 'integer'],
            'selected_occurrence.occurrence_starts_at' => ['nullable', 'date'],
            'selected_occurrence.occurrence_ends_at' => ['nullable', 'date'],
            'selected_occurrence.occurrence_original_starts_at' => ['nullable', 'date'],
            'selected_occurrence.starts_at' => ['nullable', 'date'],
            'selected_occurrence.ends_at' => ['nullable', 'date'],
            'selected_occurrence.display_key' => ['nullable', 'string'],
        ]);

        $preview = $recurringImpactService->preview(
            branch: $branch,
            selectedOccurrence: $validated['selected_occurrence'],
            action: $validated['action'],
            scope: $validated['scope'],
            changes: $validated['changes'] ?? [],
        );

        return response()->json([
            'data' => $preview,
        ]);
    }

    public function events(
        Request $request,
        Branch $branch,
        EventReadAdapterService $eventReadAdapterService,
        DisabledDayService $disabledDayService,
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

        $disabledDays = $disabledDayService->getDisabledDaysForRange($branch, $rangeStart, $rangeEnd);

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
        DisabledDayService $disabledDayService,
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

            'disabledDays' => $disabledDayService->getDisabledDaysForRange($branch, $rangeStart, $rangeEnd),

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
                ->whereIn('status', [
                    AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION,
                    AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
                    AppointmentRequest::STATUS_MANUALLY_VERIFIED,
                ])
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
        EmailNotificationService $emailNotificationService,
        PatientDirectoryService $patientDirectoryService,
        PatientMatchingService $patientMatchingService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $appointmentRequest->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'force_create_patient' => ['nullable', 'boolean'],
            'override_unverified' => ['nullable', 'boolean'],
            'manual_verification_reason' => ['nullable', 'string', 'max:1000'],
            'update_existing_patient_contact' => ['nullable', 'boolean'],
            'selected_patient' => ['nullable', 'array'],
            'selected_patient.patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'selected_patient.patient_name' => ['nullable', 'string', 'max:255'],
            'selected_patient.patient_email' => ['nullable', 'email', 'max:255'],
            'selected_patient.patient_phone' => ['nullable', 'string', 'max:255'],
            'selected_patient.patient_birth_number' => ['nullable', 'string', 'max:255'],
        ]);

        if (! in_array($appointmentRequest->status, [
            AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
            AppointmentRequest::STATUS_MANUALLY_VERIFIED,
            AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION,
            AppointmentRequest::STATUS_EXPIRED,
        ], true)) {
            throw ValidationException::withMessages([
                'appointment_request' => 'Túto požiadavku už nie je možné potvrdiť.',
            ]);
        }

        if (! $appointmentRequest->isEmailVerifiedOrManuallyVerified()) {
            if (! $request->boolean('override_unverified')) {
                throw ValidationException::withMessages([
                    'override_unverified' => 'Požiadavka musí byť emailovo overená alebo manuálne overená.',
                ]);
            }

            if (! filled($validated['manual_verification_reason'] ?? null)) {
                throw ValidationException::withMessages([
                    'manual_verification_reason' => 'Pri manuálnom overení je potrebné uviesť dôvod.',
                ]);
            }

            $appointmentRequest->forceFill([
                'status' => AppointmentRequest::STATUS_MANUALLY_VERIFIED,
                'manually_verified_at' => now(),
                'manually_verified_by' => $request->user()?->id,
                'manual_verification_reason' => $validated['manual_verification_reason'],
            ])->save();
        }

        $selectedPatientId = data_get($validated, 'selected_patient.patient_id');
        $resolvedPatientId = (int) ($validated['patient_id'] ?? $selectedPatientId ?? 0);
        $forceCreatePatient = $request->boolean('force_create_patient', false);

        if ($resolvedPatientId <= 0) {
            if (! $forceCreatePatient) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Pred potvrdením je potrebné vybrať existujúceho pacienta alebo explicitne vytvoriť nového.',
                ]);
            }

            $matches = $patientMatchingService->findMatchesForRequest($appointmentRequest);
            $hasStrongMatch = $matches->contains(fn (array $match): bool => in_array($match['confidence'], [
                'exact_email',
                'exact_phone',
                'name_and_birth_date',
                'name_and_email',
                'name_and_phone',
            ], true));

            if ($hasStrongMatch && ! $forceCreatePatient) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Našli sa silné zhody s existujúcim pacientom. Vyberte pacienta alebo potvrďte vytvorenie nového.',
                ]);
            }

            $createdPatient = $patientDirectoryService->savePatient(
                branch: $branch,
                name: data_get($validated, 'selected_patient.patient_name') ?: $appointmentRequest->patient_name,
                email: data_get($validated, 'selected_patient.patient_email') ?: $appointmentRequest->patient_email,
                phone: data_get($validated, 'selected_patient.patient_phone') ?: $appointmentRequest->patient_phone,
                birthNumber: data_get($validated, 'selected_patient.patient_birth_number') ?: $appointmentRequest->patient_birth_number,
            );

            if (! $createdPatient) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Pred potvrdením je potrebné vybrať alebo vytvoriť pacienta.',
                ]);
            }

            $resolvedPatientId = (int) $createdPatient->id;
        }

        $patient = Patient::query()
            ->where('branch_id', $branch->id)
            ->whereKey($resolvedPatientId)
            ->first();

        if (! $patient) {
            throw ValidationException::withMessages([
                'patient_id' => 'Vybraný pacient nepatrí do tejto pobočky.',
            ]);
        }

        if ($request->boolean('update_existing_patient_contact') && data_get($validated, 'selected_patient')) {
            $before = $patient->only(['patient_email', 'patient_phone', 'patient_birth_number']);

            $patient->update([
                'patient_email' => data_get($validated, 'selected_patient.patient_email') ?: $patient->patient_email,
                'patient_phone' => data_get($validated, 'selected_patient.patient_phone') ?: $patient->patient_phone,
                'patient_birth_number' => data_get($validated, 'selected_patient.patient_birth_number') ?: $patient->patient_birth_number,
                'last_used_at' => now(),
            ]);

            AppointmentRequestAuditLog::query()->create([
                'appointment_request_id' => $appointmentRequest->id,
                'branch_id' => $branch->id,
                'action' => 'patient_contact_updated',
                'reason' => 'explicit_admin_action',
                'payload' => [
                    'before' => $before,
                    'after' => $patient->fresh()->only(['patient_email', 'patient_phone', 'patient_birth_number']),
                ],
                'performed_by' => $request->user()?->id,
            ]);
        }

        $startsAt = Carbon::parse($validated['starts_at']);

        $event = $convertAppointmentRequestToEventAction->execute($branch, $appointmentRequest, [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes((int) max(15, $appointmentRequest->total_duration_minutes)),
            'patient_id' => $resolvedPatientId,
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

        $emailNotificationService->dispatch('request.accepted_as_booking', [
            'appointment_request' => $appointmentRequest,
            'event' => $event,
        ]);

        return back()->with('success', 'Ziadost bola presunuta do kalendara.');
    }

    public function cancelAppointmentRequest(
        Request $request,
        Branch $branch,
        AppointmentRequest $appointmentRequest,
        EmailNotificationService $emailNotificationService,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $appointmentRequest->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $requireRejectionReason = (bool) data_get($branch->booking_settings ?? [], 'require_rejection_reason', false);

        if ($requireRejectionReason && ! filled($validated['notification_reason'] ?? null)) {
            throw ValidationException::withMessages([
                'notification_reason' => 'Dôvod zamietnutia je povinný.',
            ]);
        }

        $appointmentRequest->update([
            'status' => AppointmentRequest::STATUS_REJECTED,
            'rejected_reason' => $validated['notification_reason'] ?? null,
        ]);

        if ($request->boolean('notify_patient', true)) {
            $emailNotificationService->dispatch('request.rejected', [
                'appointment_request' => $appointmentRequest,
                'reason' => $validated['notification_reason'] ?? null,
            ]);
        }

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'appointment_request_cancelled',
            appointmentRequestId: $appointmentRequest->id,
        );

        return back()->with('success', 'Žiadosť bola zrušená.');
    }

    public function manuallyVerifyAppointmentRequest(
        Request $request,
        Branch $branch,
        AppointmentRequest $appointmentRequest,
    ): RedirectResponse {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $appointmentRequest->branch_id !== (int) $branch->id, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $appointmentRequest->forceFill([
            'status' => AppointmentRequest::STATUS_MANUALLY_VERIFIED,
            'manually_verified_at' => now(),
            'manually_verified_by' => $request->user()?->id,
            'manual_verification_reason' => $validated['reason'],
        ])->save();

        AppointmentRequestAuditLog::query()->create([
            'appointment_request_id' => $appointmentRequest->id,
            'branch_id' => $branch->id,
            'action' => 'manual_verification',
            'reason' => $validated['reason'],
            'payload' => null,
            'performed_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Požiadavka bola manuálne overená.');
    }

    private function getPendingAppointmentRequests(Branch $branch)
    {
        return AppointmentRequest::query()
            ->where('branch_id', $branch->id)
            ->whereIn('status', [
                AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION,
                AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
                AppointmentRequest::STATUS_MANUALLY_VERIFIED,
                AppointmentRequest::STATUS_EXPIRED,
            ])
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
                'preferred_starts_at' => $appointmentRequest->preferred_starts_at?->toIso8601String(),
                'preferred_time_note' => $appointmentRequest->preferred_time_note,
                'total_duration_minutes' => $appointmentRequest->total_duration_minutes,
                'status' => $appointmentRequest->status,
                'email_verified_at' => $appointmentRequest->email_verified_at?->toIso8601String(),
                'manually_verified_at' => $appointmentRequest->manually_verified_at?->toIso8601String(),
                'manual_verification_reason' => $appointmentRequest->manual_verification_reason,
                'patient_name' => $appointmentRequest->patient_name,
                'patient_email' => $appointmentRequest->patient_email,
                'patient_phone' => $appointmentRequest->patient_phone,
                'patient_birth_number' => $appointmentRequest->patient_birth_number,
                'patient_note' => $appointmentRequest->patient_note,
                'matches' => app(PatientMatchingService::class)->findMatchesForRequest($appointmentRequest)->values()->all(),
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