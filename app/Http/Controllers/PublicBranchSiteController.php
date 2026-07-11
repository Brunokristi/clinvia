<?php

namespace App\Http\Controllers;

use App\Events\BranchCalendarUpdated;
use App\Models\AppointmentRequest;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use App\Modules\Calendar\Actions\AddGroupEventParticipantAction;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Notifications\ContactFormSubmittedNotification;
use App\Services\BookingAvailabilityService;
use App\Services\AppointmentRequestVerificationService;
use App\Services\BranchInboxMessageService;
use App\Services\EmailNotificationService;
use App\Services\PatientMatchingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicBranchSiteController extends Controller
{
    public function home(Branch $branch): Response
    {
        $this->ensurePublicSiteIsEnabled($branch);

        $branch->load([
            'company',
            'publicSite',
            'contacts',
            'openingHours.intervals',
            'employees',
            'services.category',
        ]);

        return Inertia::render($this->templateView($branch, 'Home'), [
            'branch' => $this->branchData($branch),
            'featuredServices' => $branch->services
                ->where('is_active', true)
                ->values()
                ->map(fn ($service) => $this->serviceCardData($service)),
        ]);
    }

    public function services(Branch $branch): Response
    {
        $this->ensurePublicSiteIsEnabled($branch);

        $branch->load([
            'company',
            'publicSite',
            'services.category',
        ]);

        return Inertia::render($this->templateView($branch, 'Services'), [
            'branch' => $this->branchData($branch),
            'services' => $branch->services
                ->where('is_active', true)
                ->values()
                ->map(fn ($service) => $this->serviceCardData($service)),
        ]);
    }

    public function service(Branch $branch, Service $service): Response
    {
        $this->ensurePublicSiteIsEnabled($branch);
        $this->ensureServiceBelongsToBranch($branch, $service);

        $branch->load([
            'company',
            'publicSite',
        ]);

        $service->load([
            'category',
            'information',
            'necessities',
            'steps',
            'tags',
            'files',
        ]);

        return Inertia::render($this->templateView($branch, 'ServiceShow'), [
            'branch' => $this->branchData($branch),
            'service' => $this->serviceData($service),
        ]);
    }

    public function contact(Branch $branch): Response
    {
        $this->ensurePublicSiteIsEnabled($branch);

        $branch->load([
            'company',
            'publicSite',
            'contacts',
            'openingHours.intervals',
            'employees',
        ]);

        return Inertia::render($this->templateView($branch, 'Contact'), [
            'branch' => $this->branchData($branch),
        ]);
    }

    public function booking(
        Request $request,
        Branch $branch,
        BookingAvailabilityService $bookingAvailabilityService,
        PatientMatchingService $patientMatchingService,
    ): Response {
        $this->ensurePublicSiteIsEnabled($branch);
        $this->ensureBranchBookingIsEnabled($branch);

        $branch->load([
            'company',
            'publicSite',
            'contacts',
            'openingHours.intervals',
            'employees',
            'services.category',
        ]);

        $bookingSettings = $this->bookingSettings($branch);
        $selectedDate = Carbon::parse($request->string('date', now()->toDateString()));
        $selectedServiceIds = $this->normalizeSelectedServiceIds($request);

        $bookableServices = $branch->services
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->values();

        if (! $bookingSettings['allow_service_selection'] && $bookableServices->isNotEmpty()) {
            $selectedServiceIds = [
                (int) $bookableServices->first()->id,
            ];
        }

        $selectedServices = $bookableServices
            ->whereIn('id', $selectedServiceIds)
            ->values();

        $availableSlots = collect();
        $availableOptions = collect();

        $canBookExactSlots = false;
        $canSubmitGeneralRequest = $selectedServices->isNotEmpty()
            && $bookingSettings['allow_appointment_requests'];

        if ($selectedServices->isNotEmpty()) {
            $availableSlots = $bookingAvailabilityService->getAvailableSlotsForServices(
                branch: $branch,
                services: $selectedServices,
                date: $selectedDate,
            );

            $canBookExactSlots = $availableSlots->isNotEmpty();
        }

        if ($selectedServices->isNotEmpty() && $bookingSettings['allow_appointment_requests']) {
            $availableOptions = $this->getAvailableRequestOptions(
                branch: $branch,
                selectedServices: $selectedServices,
                fromDate: $selectedDate,
            );
        }

        $isDirectBookingEligible = $this->isDirectBookingEligibleContext(
            request: $request,
            branch: $branch,
            bookingSettings: $bookingSettings,
            patientMatchingService: $patientMatchingService,
            selectedServiceIds: $selectedServiceIds,
        );

        return Inertia::render($this->templateView($branch, 'Booking'), [
            'branch' => $this->branchData($branch),
            'services' => $bookableServices
                ->map(fn (Service $service) => $this->serviceCardData($service))
                ->values(),
            'selectedServiceIds' => $selectedServiceIds,
            'selectedDate' => $selectedDate->toDateString(),
            'canBookExactSlots' => $canBookExactSlots,
            'canSubmitGeneralRequest' => $canSubmitGeneralRequest,
            'availableSlots' => $availableSlots
                ->map(fn (Event $event) => [
                    'id' => $event->id,
                    'capacity_window_id' => $event->id,
                    'service_id' => $event->groupDetail?->service_id,
                    'service_name' => $event->groupDetail?->service_name,
                    'starts_at' => $event->starts_at?->toDateTimeString(),
                    'ends_at' => $event->ends_at?->toDateTimeString(),
                    'capacity' => (int) ($event->groupDetail?->capacity ?? 0),
                    'confirmed_bookings_count' => (int) ($event->groupDetail?->reserved_places ?? 0),
                    'free_capacity' => max(
                        0,
                        (int) ($event->groupDetail?->capacity ?? 0) - (int) ($event->groupDetail?->reserved_places ?? 0),
                    ),
                ])
                ->values(),
            'availableOptions' => $availableOptions->values(),
            'availableOptionsPagination' => null,
            'bookingSettings' => $bookingSettings,
            'isDirectBookingEligible' => $isDirectBookingEligible,
            'flowInfoText' => [
                'anonymous_request' => 'Po odoslaní požiadavky Vám príde email na potvrdenie. Po potvrdení požiadavku skontrolujeme a termín Vám potvrdíme.',
                'verified_direct' => 'Po potvrdení sa termín okamžite zapíše do kalendára.',
            ],
            'verifiedPatientContext' => [
                'verified_patient_email' => $patientMatchingService->normalizeEmail(
                    $request->string('verified_patient_email')->toString(),
                ),
            ],
        ]);
    }

    public function storeBooking(
        Request $request,
        Branch $branch,
        AddGroupEventParticipantAction $addGroupEventParticipantAction,
        BranchInboxMessageService $inboxMessageService,
        EmailNotificationService $emailNotificationService,
        PatientMatchingService $patientMatchingService,
    ): RedirectResponse {
        $this->ensurePublicSiteIsEnabled($branch);
        $this->ensureBranchBookingIsEnabled($branch);

        $bookingSettings = $this->bookingSettings($branch);

        if (filled($request->input('patient_id'))) {
            throw ValidationException::withMessages([
                'patient_id' => 'Pacient sa pri verejnej požiadavke priraďuje až po overení a kontrole administrátorom.',
            ]);
        }

        $validated = $request->validate([
            'request_type' => ['nullable', 'string', 'in:appointment_request,group_event_request,preferred_period,general'],
            'branch_id' => ['nullable', 'integer'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'service_ids' => ['nullable', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'capacity_window_id' => ['nullable', 'integer', 'exists:events,id'],
            'starts_at' => ['nullable', 'date'],
            'requested_starts_at' => ['nullable', 'date'],
            'verified_patient_email' => ['nullable', 'email', 'max:255'],
            'is_for_someone_else' => ['nullable', 'boolean'],
            'requester_name' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->boolean('is_for_someone_else') || ! filled($request->input('patient_name')))],
            'requester_email' => ['nullable', 'email', 'max:255', Rule::requiredIf(fn () => $request->boolean('is_for_someone_else') || ! filled($request->input('patient_email')))],
            'requester_phone' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->boolean('is_for_someone_else') || ! filled($request->input('patient_phone')))],
            'preferred_option_id' => ['nullable', 'string'],
            'preferred_date' => ['nullable', 'date'],
            'preferred_period' => ['nullable', 'string', 'in:morning,forenoon,afternoon,evening'],
            'preferred_starts_at' => ['nullable', 'date'],
            'preferred_time_note' => ['nullable', 'string', 'max:500'],
            'first_name' => ['required_without:patient_name', 'nullable', 'string', 'max:255'],
            'last_name' => ['required_without:patient_name', 'nullable', 'string', 'max:255'],
            'patient_name' => ['required_without:requester_name', 'nullable', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'patient_date_of_birth' => ['nullable', 'date'],
            'patient_birth_number' => ['nullable', 'string', 'max:255'],
            'patient_note' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:2000'],
            'privacy_consent' => ['nullable'],
            'consent' => ['nullable'],
            'website' => ['nullable', 'string', 'max:255'],
            'form_started_at' => ['nullable'],
        ]);

        if (filled($validated['branch_id'] ?? null) && (int) $validated['branch_id'] !== (int) $branch->id) {
            throw ValidationException::withMessages([
                'branch_id' => 'Vybraná pobočka nie je platná.',
            ]);
        }

        if (empty($validated['service_ids']) && filled($validated['service_id'] ?? null)) {
            $validated['service_ids'] = [(int) $validated['service_id']];
        }

        if (empty($validated['service_ids'])) {
            throw ValidationException::withMessages([
                'service_id' => 'Vyberte aspoň jednu službu.',
            ]);
        }

        if (! ($request->boolean('privacy_consent') || $request->boolean('consent'))) {
            throw ValidationException::withMessages([
                'consent' => 'Musíte súhlasiť so spracovaním osobných údajov.',
            ]);
        }

        $validated['privacy_consent'] = true;
        $validated['patient_note'] = $validated['patient_note'] ?? ($validated['note'] ?? null);
        $validated['date_of_birth'] = $validated['date_of_birth'] ?? ($validated['patient_date_of_birth'] ?? null);
        $validated['preferred_starts_at'] = $validated['preferred_starts_at']
            ?? ($validated['requested_starts_at'] ?? null)
            ?? ($validated['starts_at'] ?? null);

        if (empty($validated['preferred_date']) && ! empty($validated['preferred_starts_at'])) {
            $validated['preferred_date'] = Carbon::parse($validated['preferred_starts_at'])->toDateString();
        }

        $isForSomeoneElse = (bool) ($validated['is_for_someone_else'] ?? false);

        $validated['patient_email'] = $patientMatchingService->normalizeEmail($validated['patient_email'] ?? null);
        $validated['patient_phone'] = $patientMatchingService->normalizePhone($validated['patient_phone'] ?? null);
        $validated['requester_email'] = $patientMatchingService->normalizeEmail($validated['requester_email'] ?? null);
        $validated['requester_phone'] = $patientMatchingService->normalizePhone($validated['requester_phone'] ?? null);

        if (! filled($validated['patient_name'] ?? null)) {
            $validated['patient_name'] = trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));
        }

        if (! $isForSomeoneElse) {
            $validated['requester_name'] = $validated['requester_name'] ?? $validated['patient_name'];
            $validated['requester_email'] = $validated['requester_email'] ?? $validated['patient_email'];
            $validated['requester_phone'] = $validated['requester_phone'] ?? $validated['patient_phone'];
            $validated['patient_name'] = $validated['patient_name'] ?? $validated['requester_name'];
            $validated['patient_email'] = $validated['patient_email'] ?? $validated['requester_email'];
            $validated['patient_phone'] = $validated['patient_phone'] ?? $validated['requester_phone'];
        }

        if (! empty($validated['website'])) {
            return back()->with('success', 'Ďakujeme, vašu požiadavku sme prijali.');
        }

        $outcome = $this->decidePublicBookingOutcome(
            request: $request,
            branch: $branch,
            bookingSettings: $bookingSettings,
            validated: $validated,
            patientMatchingService: $patientMatchingService,
        );

        if ($outcome['type'] === 'direct_booking') {
            $resolvedPatientId = (int) $outcome['resolved_patient_id'];

            return $this->storeExactCapacityWindowBooking(
                branch: $branch,
                validated: $validated,
                addGroupEventParticipantAction: $addGroupEventParticipantAction,
                resolvedPatientId: $resolvedPatientId,
            );
        }

        $validated = $this->prepareRequestFallbackPayload(
            branch: $branch,
            validated: $validated,
        );

        if (! $bookingSettings['allow_appointment_requests']) {
            throw ValidationException::withMessages([
                'service_ids' => 'Požiadavky na termín sú momentálne vypnuté.',
            ]);
        }

        return $this->storeAppointmentRequest(
            branch: $branch,
            validated: $validated,
            inboxMessageService: $inboxMessageService,
            emailNotificationService: $emailNotificationService,
            verificationService: app(AppointmentRequestVerificationService::class),
        );
    }

    public function storeContactMessage(
        Request $request,
        Branch $branch,
        BranchInboxMessageService $inboxMessageService,
    ): RedirectResponse {
        $this->ensurePublicSiteIsEnabled($branch);

        $validated = $request->validate([
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_email' => ['nullable', 'email', 'max:255'],
            'sender_phone' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $inboxMessageService->createForContactForm(
            branch: $branch,
            senderName: $validated['sender_name'],
            senderEmail: $validated['sender_email'] ?? null,
            senderPhone: $validated['sender_phone'] ?? null,
            body: $validated['body'],
        );

        if (! empty($validated['sender_email'])) {
            Notification::route('mail', $validated['sender_email'])
                ->notify(new ContactFormSubmittedNotification(
                    branch: $branch,
                    senderName: $validated['sender_name'],
                ));
        }

        return back()->with('success', 'Správa bola odoslaná.');
    }

    public function verifyAppointmentRequestEmail(
        Request $request,
        Branch $branch,
        AppointmentRequest $appointmentRequest,
        AppointmentRequestVerificationService $verificationService,
        BranchInboxMessageService $inboxMessageService,
        EmailNotificationService $emailNotificationService,
    ): RedirectResponse {
        $this->ensurePublicSiteIsEnabled($branch);
        $this->ensureBranchBookingIsEnabled($branch);

        abort_if((int) $appointmentRequest->branch_id !== (int) $branch->id, 404);

        $token = (string) $request->query('token', '');

        if ($token === '') {
            return redirect()
                ->route('public.branch.booking', ['branch' => $branch->slug])
                ->withErrors(['verification' => 'Overovací odkaz je neplatný.']);
        }

        if (! $verificationService->verify($appointmentRequest, $token)) {
            return redirect()
                ->route('public.branch.booking', ['branch' => $branch->slug])
                ->withErrors(['verification' => 'Overovací odkaz je neplatný alebo expirovaný.']);
        }

        $appointmentRequest->refresh();
        $appointmentRequest->loadMissing(['branch', 'services']);

        $inboxMessageService->createForAppointmentRequest($appointmentRequest);

        $emailNotificationService->dispatch('request.created', [
            'appointment_request' => $appointmentRequest,
            'skip_patient_notification' => true,
        ]);

        BranchCalendarUpdated::dispatch(
            branchId: $appointmentRequest->branch_id,
            action: 'appointment_request_verified',
            appointmentRequestId: $appointmentRequest->id,
        );

        return redirect()
            ->route('public.branch.booking', ['branch' => $branch->slug])
            ->with('success', 'Email bol overený. Pobočka teraz vašu požiadavku spracuje.');
    }

    public function resendAppointmentRequestVerification(
        Request $request,
        Branch $branch,
        AppointmentRequestVerificationService $verificationService,
        PatientMatchingService $patientMatchingService,
        EmailNotificationService $emailNotificationService,
    ): RedirectResponse {
        $this->ensurePublicSiteIsEnabled($branch);
        $this->ensureBranchBookingIsEnabled($branch);

        $validated = $request->validate([
            'request_id' => ['required', 'integer', 'exists:appointment_requests,id'],
            'patient_email' => ['required', 'email'],
        ]);

        $appointmentRequest = AppointmentRequest::query()
            ->where('branch_id', $branch->id)
            ->whereKey((int) $validated['request_id'])
            ->firstOrFail();

        if ($appointmentRequest->isEmailVerifiedOrManuallyVerified()) {
            return redirect()
                ->route('public.branch.booking', ['branch' => $branch->slug])
                ->with('success', 'Táto požiadavka je už overená.');
        }

        $normalizedEmail = $patientMatchingService->normalizeEmail($validated['patient_email']);

        if ($normalizedEmail !== $appointmentRequest->normalized_email) {
            return redirect()
                ->route('public.branch.booking', ['branch' => $branch->slug])
                ->withErrors(['patient_email' => 'Email sa nezhoduje s požiadavkou.']);
        }

        $token = $verificationService->issueToken($appointmentRequest);

        $verificationUrl = route('public.branch.booking.request.verify', [
            'branch' => $branch->slug,
            'appointmentRequest' => $appointmentRequest->id,
            'token' => $token,
        ]);

        $emailNotificationService->dispatch('request.verification', [
            'appointment_request' => $appointmentRequest->fresh(['branch', 'services']),
            'verification_url' => $verificationUrl,
        ]);

        return redirect()
            ->route('public.branch.booking', ['branch' => $branch->slug])
            ->with('success', 'Overovací email bol znovu odoslaný.');
    }

    private function normalizeSelectedServiceIds(Request $request): array
    {
        $services = $request->input('services', []);

        if (is_string($services)) {
            $services = explode(',', $services);
        }

        if (! is_array($services)) {
            return [];
        }

        return collect($services)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function getAvailableRequestOptions(
        Branch $branch,
        Collection $selectedServices,
        Carbon $fromDate,
    ): Collection {
        if ($selectedServices->isEmpty()) {
            return collect();
        }

        $totalDurationMinutes = $selectedServices->sum(function (Service $service) {
            return (int) ($service->duration_minutes ?? 0);
        });

        if ($totalDurationMinutes <= 0) {
            return collect();
        }

        $selectedServiceIds = $selectedServices
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $rules = Event::query()
            ->where('branch_id', $branch->id)
            ->where('type', EventType::AvailabilityRule)
            ->whereNotIn('status', ['cancelled'])
            ->with('services')
            ->get()
            ->filter(function (Event $rule) use ($selectedServiceIds) {
                return $this->ruleAllowsAllSelectedServices($rule, $selectedServiceIds);
            })
            ->values();

        if ($rules->isEmpty()) {
            return collect();
        }

        $options = collect();

        $start = $fromDate->copy()->startOfDay();

        if ($start->isPast()) {
            $start = now()->copy()->startOfDay();
        }

        $end = $start->copy()->addDays(30)->endOfDay();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            foreach ($this->requestPeriods() as $period => $periodData) {
                $periodStartsAt = Carbon::parse($date->toDateString() . ' ' . $periodData['starts_at']);
                $periodEndsAt = Carbon::parse($date->toDateString() . ' ' . $periodData['ends_at']);

                if ($periodEndsAt->isPast()) {
                    continue;
                }

                $availableMinutes = 0;

                foreach ($rules as $rule) {
                    if (! $this->ruleAppliesOnDate($rule, $date)) {
                        continue;
                    }

                    $availableMinutes += $this->availableRuleMinutesForPeriod(
                        branch: $branch,
                        rule: $rule,
                        date: $date,
                        periodStartsAt: $periodStartsAt,
                        periodEndsAt: $periodEndsAt,
                    );
                }

                if ($availableMinutes < $totalDurationMinutes) {
                    continue;
                }

                $options->push([
                    'id' => $date->toDateString() . '_' . $period,
                    'date' => $date->toDateString(),
                    'date_label' => $date->translatedFormat('l j. n. Y'),
                    'period' => $period,
                    'period_label' => $periodData['label'],
                    'starts_at' => $periodStartsAt->toDateTimeString(),
                    'ends_at' => $periodEndsAt->toDateTimeString(),
                    'remaining_minutes' => max(0, $availableMinutes),
                    'total_duration_minutes' => $totalDurationMinutes,
                ]);
            }
        }

        return $options
            ->sortBy([
                ['date', 'asc'],
                ['starts_at', 'asc'],
            ])
            ->values()
            ->take(20);
    }

    private function requestPeriods(): array
    {
        return [
            'morning' => [
                'label' => 'Ráno',
                'starts_at' => '06:00:00',
                'ends_at' => '09:00:00',
            ],
            'forenoon' => [
                'label' => 'Dopoludnia',
                'starts_at' => '09:00:00',
                'ends_at' => '12:00:00',
            ],
            'afternoon' => [
                'label' => 'Popoludní',
                'starts_at' => '12:00:00',
                'ends_at' => '17:00:00',
            ],
            'evening' => [
                'label' => 'Večer',
                'starts_at' => '17:00:00',
                'ends_at' => '21:00:00',
            ],
        ];
    }

    private function availableRuleMinutesForPeriod(
        Branch $branch,
        Event $rule,
        Carbon $date,
        Carbon $periodStartsAt,
        Carbon $periodEndsAt,
    ): int {
        if (app(\App\Services\DisabledDayService::class)->isDisabled($branch, $date)) {
            return 0;
        }

        $ruleStartsAt = Carbon::parse($date->toDateString() . ' ' . $this->timeValue($rule->starts_at));
        $ruleEndsAt = Carbon::parse($date->toDateString() . ' ' . $this->timeValue($rule->ends_at));

        if ($ruleEndsAt->lessThanOrEqualTo($ruleStartsAt)) {
            return 0;
        }

        $overlapStartsAt = $ruleStartsAt->greaterThan($periodStartsAt)
            ? $ruleStartsAt
            : $periodStartsAt;

        $overlapEndsAt = $ruleEndsAt->lessThan($periodEndsAt)
            ? $ruleEndsAt
            : $periodEndsAt;

        if ($overlapEndsAt->lessThanOrEqualTo($overlapStartsAt)) {
            return 0;
        }

        if ($overlapEndsAt->isPast()) {
            return 0;
        }

        if ($overlapStartsAt->isPast()) {
            $overlapStartsAt = now();
        }

        if ($overlapEndsAt->lessThanOrEqualTo($overlapStartsAt)) {
            return 0;
        }

        $availableMinutes = $overlapStartsAt->diffInMinutes($overlapEndsAt);

        $usedBookingMinutes = $this->confirmedBookingMinutesInsideRange(
            branch: $branch,
            startsAt: $overlapStartsAt,
            endsAt: $overlapEndsAt,
        );

        $pendingRequestMinutes = $this->pendingRequestMinutesForRuleRange(
            branch: $branch,
            date: $date,
            ruleStartsAt: $overlapStartsAt,
            ruleEndsAt: $overlapEndsAt,
        );

        return max(0, $availableMinutes - $usedBookingMinutes - $pendingRequestMinutes);
    }

    private function timeValue(mixed $value): string
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('H:i:s');
        }

        return Carbon::parse((string) $value)->format('H:i:s');
    }

    private function periodFromRuleStart(Carbon $startsAt): string
    {
        $hour = (int) $startsAt->format('H');

        if ($hour < 9) {
            return 'morning';
        }

        if ($hour < 12) {
            return 'forenoon';
        }

        if ($hour < 17) {
            return 'afternoon';
        }

        return 'evening';
    }

    private function formatRulePeriodLabel(Carbon $startsAt, Carbon $endsAt): string
    {
        return $startsAt->format('H:i') . ' – ' . $endsAt->format('H:i');
    }

    private function ruleAllowsAllSelectedServices(
        Event $rule,
        Collection $selectedServiceIds,
    ): bool {
        $selectedServiceIds = $selectedServiceIds
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $ruleServiceIds = $rule->services
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return $selectedServiceIds
            ->diff($ruleServiceIds)
            ->isEmpty();
    }

    private function confirmedBookingMinutesInsideRange(
        Branch $branch,
        Carbon $startsAt,
        Carbon $endsAt,
    ): int {
        return Event::query()
            ->where('branch_id', $branch->id)
            ->where('type', EventType::Booking)
            ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->get()
            ->sum(function (Event $booking) use ($startsAt, $endsAt) {
                $bookingStartsAt = $booking->starts_at;
                $bookingEndsAt = $booking->ends_at;

                if (! $bookingStartsAt || ! $bookingEndsAt) {
                    return 0;
                }

                $overlapStartsAt = $bookingStartsAt->greaterThan($startsAt)
                    ? $bookingStartsAt
                    : $startsAt;

                $overlapEndsAt = $bookingEndsAt->lessThan($endsAt)
                    ? $bookingEndsAt
                    : $endsAt;

                if ($overlapEndsAt->lessThanOrEqualTo($overlapStartsAt)) {
                    return 0;
                }

                return $overlapStartsAt->diffInMinutes($overlapEndsAt);
            });
    }

    private function pendingRequestMinutesForRuleRange(
        Branch $branch,
        Carbon $date,
        Carbon $ruleStartsAt,
        Carbon $ruleEndsAt,
    ): int {
        return AppointmentRequest::query()
            ->where('branch_id', $branch->id)
            ->whereDate('preferred_date', $date->toDateString())
            ->whereIn('status', [
                AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION,
                AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
                AppointmentRequest::STATUS_MANUALLY_VERIFIED,
            ])
            ->where('request_type', 'preferred_period')
            ->get()
            ->sum(function (AppointmentRequest $appointmentRequest) use ($ruleStartsAt, $ruleEndsAt) {
                $preferredPeriod = $appointmentRequest->preferred_period;

                if (! $preferredPeriod) {
                    return 0;
                }

                $requestStartsAt = $this->periodStartsAtForDate(
                    date: $ruleStartsAt->copy()->startOfDay(),
                    period: $preferredPeriod,
                );

                $requestEndsAt = $this->periodEndsAtForDate(
                    date: $ruleStartsAt->copy()->startOfDay(),
                    period: $preferredPeriod,
                );

                if (! $requestStartsAt || ! $requestEndsAt) {
                    return 0;
                }

                if ($requestStartsAt->gte($ruleEndsAt) || $requestEndsAt->lte($ruleStartsAt)) {
                    return 0;
                }

                return (int) $appointmentRequest->total_duration_minutes;
            });
    }

    private function periodStartsAtForDate(Carbon $date, string $period): ?Carbon
    {
        return match ($period) {
            'morning' => Carbon::parse($date->toDateString() . ' 06:00:00'),
            'forenoon' => Carbon::parse($date->toDateString() . ' 09:00:00'),
            'afternoon' => Carbon::parse($date->toDateString() . ' 12:00:00'),
            'evening' => Carbon::parse($date->toDateString() . ' 17:00:00'),
            default => null,
        };
    }

    private function periodEndsAtForDate(Carbon $date, string $period): ?Carbon
    {
        return match ($period) {
            'morning' => Carbon::parse($date->toDateString() . ' 09:00:00'),
            'forenoon' => Carbon::parse($date->toDateString() . ' 12:00:00'),
            'afternoon' => Carbon::parse($date->toDateString() . ' 17:00:00'),
            'evening' => Carbon::parse($date->toDateString() . ' 21:00:00'),
            default => null,
        };
    }

    private function ruleAppliesOnDate(Event $rule, Carbon $date): bool
    {
        $rawDate = $rule->starts_at?->toDateString();

        if (app(\App\Services\DisabledDayService::class)->isDisabled($rule->branch, $date)) {
            return false;
        }

        if (! $rawDate) {
            return false;
        }

        $ruleDate = Carbon::parse($rawDate)->startOfDay();
        $targetDate = $date->copy()->startOfDay();

        if ($targetDate->lt($ruleDate)) {
            return false;
        }

        $excludedDates = collect(data_get($rule->metadata, 'recurrence_excluded_dates', []))
            ->map(fn ($excludedDate) => Carbon::parse($excludedDate)->toDateString())
            ->all();

        if (in_array($targetDate->toDateString(), $excludedDates, true)) {
            return false;
        }

        $recurrenceEndsOn = data_get($rule->recurrence_rule, 'ends.until');

        if ($recurrenceEndsOn && $targetDate->gt(Carbon::parse($recurrenceEndsOn)->startOfDay())) {
            return false;
        }

        if (! $rule->is_recurring || empty($rule->recurrence_rule)) {
            return $targetDate->isSameDay($ruleDate);
        }

        $repeatEvery = max(1, (int) data_get($rule->recurrence_rule, 'interval', 1));
        $frequency = data_get($rule->recurrence_rule, 'frequency', 'weekly');
        $weekdays = collect(data_get($rule->recurrence_rule, 'weekdays', []))
            ->map(fn ($day) => strtoupper((string) $day))
            ->values()
            ->all();

        $isoDayCode = $this->isoWeekdayCode($targetDate);

        return match ($frequency) {
            'daily' => $ruleDate->diffInDays($targetDate) % $repeatEvery === 0,
            'monthly' => $ruleDate->diffInMonths($targetDate) % $repeatEvery === 0
                && (int) $ruleDate->day === (int) $targetDate->day,
            default => (empty($weekdays) || in_array($isoDayCode, $weekdays, true))
                && $ruleDate->diffInWeeks($targetDate) % $repeatEvery === 0,
        };
    }

    private function isoWeekdayCode(Carbon $date): string
    {
        return match ((int) $date->dayOfWeekIso) {
            1 => 'MO',
            2 => 'TU',
            3 => 'WE',
            4 => 'TH',
            5 => 'FR',
            6 => 'SA',
            default => 'SU',
        };
    }

    private function storeExactCapacityWindowBooking(
        Branch $branch,
        array $validated,
        AddGroupEventParticipantAction $addGroupEventParticipantAction,
        int $resolvedPatientId,
    ): RedirectResponse {
        if (empty($validated['capacity_window_id'])) {
            throw ValidationException::withMessages([
                'capacity_window_id' => 'Vyberte termín.',
            ]);
        }

        $selectedServiceIds = collect($validated['service_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedServiceIds->count() !== 1) {
            throw ValidationException::withMessages([
                'service_ids' => 'Pri priamom skupinovom termíne vyberte iba jednu službu.',
            ]);
        }

        $event = DB::transaction(function () use (
            $branch,
            $validated,
            $selectedServiceIds,
            $addGroupEventParticipantAction,
            $resolvedPatientId,
        ): Event {
            $groupEvent = Event::query()
                ->with(['services', 'groupDetail', 'participants'])
                ->where('branch_id', $branch->id)
                ->where('type', EventType::GroupEvent)
                ->whereKey($validated['capacity_window_id'])
                ->whereNotIn('status', ['cancelled'])
                ->lockForUpdate()
                ->firstOrFail();

            $service = $groupEvent->services
                ->firstWhere('id', (int) ($groupEvent->groupDetail?->service_id ?? 0))
                ?? $groupEvent->services->first();

            if (! $service || ! $service->is_active || ! $service->is_bookable) {
                throw ValidationException::withMessages([
                    'capacity_window_id' => 'Tento termín nie je dostupný na priamu rezerváciu.',
                ]);
            }

            if (($service->public_booking_type ?? 'appointment_request') !== 'immediate_booking') {
                throw ValidationException::withMessages([
                    'capacity_window_id' => 'Táto služba nepodporuje okamžitú rezerváciu.',
                ]);
            }

            if ($groupEvent->starts_at?->isPast()) {
                throw ValidationException::withMessages([
                    'capacity_window_id' => 'Tento termín už nie je dostupný.',
                ]);
            }

            if (app(\App\Services\DisabledDayService::class)->isDisabled($branch, $groupEvent->starts_at)) {
                throw ValidationException::withMessages([
                    'capacity_window_id' => 'Tento deň je v kalendári zakázaný.',
                ]);
            }

            $groupServiceId = (int) ($groupEvent->groupDetail?->service_id ?? $service->id);

            if ($groupServiceId !== (int) $selectedServiceIds->first()) {
                throw ValidationException::withMessages([
                    'service_ids' => 'Vybraný termín nepatrí k vybranej službe.',
                ]);
            }

            $capacity = (int) ($groupEvent->groupDetail?->capacity ?? 0);
            $reservedPlaces = (int) ($groupEvent->groupDetail?->reserved_places ?? 0);

            if ($reservedPlaces >= $capacity) {
                throw ValidationException::withMessages([
                    'capacity_window_id' => 'Tento termín je už obsadený.',
                ]);
            }

            $addGroupEventParticipantAction->execute($groupEvent, [
                'patient_id' => $resolvedPatientId,
                'participant_name' => $validated['patient_name'],
                'participant_email' => $validated['patient_email'] ?? null,
                'participant_phone' => $validated['patient_phone'] ?? null,
                'notes' => $validated['patient_note'] ?? null,
                'status' => 'confirmed',
            ]);

            return $groupEvent;
        });

        BranchCalendarUpdated::dispatch(
            branchId: $event->branch_id,
            action: 'capacity_window_booking_created',
            capacityWindowId: $event->id,
        );

        return redirect()
            ->route('public.branch.booking', ['branch' => $branch->slug])
            ->with('success', 'Termín bol rezervovaný. Skontrolujte si email s potvrdením.');
    }

    private function resolveDirectBookingPatientId(
        Request $request,
        Branch $branch,
        array $validated,
        PatientMatchingService $patientMatchingService,
    ): int {
        $requestedPatientId = (int) ($validated['patient_id'] ?? 0);

        if ($request->user() && $request->user()->canAccessBranch($branch)) {
            if ($requestedPatientId <= 0) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Pri vytvorení rezervácie je potrebné vybrať pacienta.',
                ]);
            }

            $exists = \App\Models\Patient::query()
                ->where('branch_id', $branch->id)
                ->whereKey($requestedPatientId)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Vybraný pacient nepatrí do tejto pobočky.',
                ]);
            }

            return $requestedPatientId;
        }

        $verifiedEmail = $patientMatchingService->normalizeEmail($validated['verified_patient_email'] ?? null);

        if (! $verifiedEmail) {
            throw ValidationException::withMessages([
                'mode' => 'Priamu rezerváciu je možné vytvoriť iba pre overeného pacienta. Vytvorte požiadavku na termín.',
            ]);
        }

        $matchedPatients = \App\Models\Patient::query()
            ->where('branch_id', $branch->id)
            ->whereRaw('LOWER(patient_email) = ?', [$verifiedEmail])
            ->get(['id']);

        if ($matchedPatients->isEmpty()) {
            throw ValidationException::withMessages([
                'mode' => 'Priamu rezerváciu je možné vytvoriť iba pre overeného pacienta. Vytvorte požiadavku na termín.',
            ]);
        }

        if ($matchedPatients->count() > 1 && $requestedPatientId <= 0) {
            throw ValidationException::withMessages([
                'patient_id' => 'Pre tento email bolo nájdených viac pacientov. Vyberte konkrétneho pacienta.',
            ]);
        }

        if ($requestedPatientId > 0) {
            $isInMatches = $matchedPatients
                ->contains(fn ($patient): bool => (int) $patient->id === $requestedPatientId);

            if (! $isInMatches) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Vybraný pacient sa nezhoduje s overeným emailom.',
                ]);
            }

            return $requestedPatientId;
        }

        return (int) $matchedPatients->first()->id;
    }

    private function decidePublicBookingOutcome(
        Request $request,
        Branch $branch,
        array $bookingSettings,
        array $validated,
        PatientMatchingService $patientMatchingService,
    ): array {
        $bookingMode = $this->normalizePublicBookingMode((string) ($bookingSettings['booking_mode'] ?? 'requests_only'));
        $requestedMode = filled($validated['capacity_window_id'] ?? null) ? 'exact_slot' : 'appointment_request';

        if ($bookingMode !== 'verified_patients_direct') {
            return [
                'type' => 'appointment_request',
                'resolved_patient_id' => null,
            ];
        }

        if ($requestedMode !== 'exact_slot') {
            return [
                'type' => 'appointment_request',
                'resolved_patient_id' => null,
            ];
        }

        if (! $this->servicesAllowDirectBooking($branch, $validated['service_ids'] ?? [])) {
            return [
                'type' => 'appointment_request',
                'resolved_patient_id' => null,
            ];
        }

        $resolvedPatientId = $this->tryResolveDirectBookingPatientId(
            request: $request,
            branch: $branch,
            validated: $validated,
            patientMatchingService: $patientMatchingService,
        );

        if (! $resolvedPatientId) {
            return [
                'type' => 'appointment_request',
                'resolved_patient_id' => null,
            ];
        }

        return [
            'type' => 'direct_booking',
            'resolved_patient_id' => $resolvedPatientId,
        ];
    }

    private function prepareRequestFallbackPayload(Branch $branch, array $validated): array
    {
        $validated['mode'] = 'appointment_request';

        $selectedGroupEvent = null;
        $selectedGroupEventId = (int) ($validated['capacity_window_id'] ?? 0);

        if ($selectedGroupEventId > 0) {
            $selectedGroupEvent = Event::query()
                ->where('branch_id', $branch->id)
                ->where('type', EventType::GroupEvent)
                ->whereKey($selectedGroupEventId)
                ->first();

            if (! $selectedGroupEvent) {
                throw ValidationException::withMessages([
                    'capacity_window_id' => 'Vybraný skupinový termín nie je dostupný.',
                ]);
            }
        }

        $incomingType = (string) ($validated['request_type'] ?? '');

        $isGroupRequest = $selectedGroupEvent !== null || $incomingType === 'group_event_request';

        if ($isGroupRequest) {
            if (! $selectedGroupEvent) {
                throw ValidationException::withMessages([
                    'capacity_window_id' => 'Pre prihlásenie na skupinový termín vyberte konkrétny termín.',
                ]);
            }

            $validated['request_type'] = 'group_event_request';
            $validated['source_type'] = 'group_event';
            $validated['group_event_id'] = $selectedGroupEvent->id;
            $validated['group_event_occurrence_original_start_at'] = $selectedGroupEvent->recurrence_original_starts_at
                ? $selectedGroupEvent->recurrence_original_starts_at->toIso8601String()
                : null;
            $validated['requested_starts_at'] = $selectedGroupEvent->starts_at?->toIso8601String();
            $validated['requested_ends_at'] = $selectedGroupEvent->ends_at?->toIso8601String();
            $validated['requested_group_event_starts_at'] = $selectedGroupEvent->starts_at?->toIso8601String();
            $validated['requested_group_event_ends_at'] = $selectedGroupEvent->ends_at?->toIso8601String();
            $validated['preferred_starts_at'] = $validated['preferred_starts_at']
                ?? $selectedGroupEvent->starts_at?->toDateTimeString();
            $validated['preferred_date'] = $selectedGroupEvent->starts_at?->toDateString() ?? now()->toDateString();
            $validated['preferred_period'] = null;

            return $validated;
        }

        $validated['request_type'] = 'appointment_request';
        $validated['source_type'] = 'reservation_rule';

        $preferredDate = ! empty($validated['preferred_date'])
            ? Carbon::parse($validated['preferred_date'])->toDateString()
            : null;

        if (! $preferredDate && ! empty($validated['preferred_starts_at'])) {
            $preferredDate = Carbon::parse($validated['preferred_starts_at'])->toDateString();
        }

        $validated['preferred_date'] = $preferredDate ?: now()->toDateString();
        $validated['requested_starts_at'] = $validated['requested_starts_at']
            ?? ($validated['preferred_starts_at'] ?? ($validated['preferred_date'] . ' 00:00:00'));
        $validated['group_event_id'] = null;
        $validated['group_event_occurrence_original_start_at'] = null;
        $validated['requested_group_event_starts_at'] = null;
        $validated['requested_group_event_ends_at'] = null;

        return $validated;
    }

    private function servicesAllowDirectBooking(Branch $branch, array $serviceIds): bool
    {
        $selectedServiceIds = collect($serviceIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($selectedServiceIds->isEmpty()) {
            return false;
        }

        $services = Service::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->whereIn('id', $selectedServiceIds)
            ->get();

        if ($services->count() !== $selectedServiceIds->count()) {
            return false;
        }

        return $services->every(function (Service $service): bool {
            return ($service->public_booking_type ?? 'appointment_request') === 'immediate_booking';
        });
    }

    private function tryResolveDirectBookingPatientId(
        Request $request,
        Branch $branch,
        array $validated,
        PatientMatchingService $patientMatchingService,
    ): ?int {
        $verifiedEmail = $patientMatchingService->normalizeEmail($validated['verified_patient_email'] ?? null);

        if (! $verifiedEmail) {
            return null;
        }

        $matchedPatients = Patient::query()
            ->where('branch_id', $branch->id)
            ->whereRaw('LOWER(patient_email) = ?', [$verifiedEmail])
            ->get(['id']);

        if ($matchedPatients->count() !== 1) {
            return null;
        }

        return (int) $matchedPatients->first()->id;
    }

    private function isDirectBookingEligibleContext(
        Request $request,
        Branch $branch,
        array $bookingSettings,
        PatientMatchingService $patientMatchingService,
        array $selectedServiceIds,
    ): bool {
        if ($this->normalizePublicBookingMode((string) ($bookingSettings['booking_mode'] ?? 'requests_only')) !== 'verified_patients_direct') {
            return false;
        }

        if (! $this->servicesAllowDirectBooking($branch, $selectedServiceIds)) {
            return false;
        }

        $verifiedEmail = $patientMatchingService->normalizeEmail($request->string('verified_patient_email')->toString());

        if (! $verifiedEmail) {
            return false;
        }

        return Patient::query()
            ->where('branch_id', $branch->id)
            ->whereRaw('LOWER(patient_email) = ?', [$verifiedEmail])
            ->count() === 1;
    }

    private function storeAppointmentRequest(
        Branch $branch,
        array $validated,
        BranchInboxMessageService $inboxMessageService,
        EmailNotificationService $emailNotificationService,
        AppointmentRequestVerificationService $verificationService,
    ): RedirectResponse {
        $serviceIds = collect($validated['service_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $services = Service::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->whereIn('id', $serviceIds)
            ->get();

        if ($services->count() !== $serviceIds->count()) {
            throw ValidationException::withMessages([
                'service_ids' => 'Niektoré služby nie sú dostupné.',
            ]);
        }

        $totalDurationMinutes = $services->sum(function (Service $service) {
            return (int) ($service->duration_minutes ?? 0);
        });

        if ($totalDurationMinutes <= 0) {
            throw ValidationException::withMessages([
                'service_ids' => 'Vybrané služby nemajú nastavené trvanie.',
            ]);
        }

        $requestType = (string) ($validated['request_type'] ?? 'appointment_request');

        if ($requestType === 'group_event_request' && empty($validated['group_event_id'])) {
            throw ValidationException::withMessages([
                'group_event_id' => 'Prihlásenie na skupinový termín vyžaduje konkrétny skupinový termín.',
            ]);
        }

        if ($requestType === 'appointment_request' && ! empty($validated['preferred_period'])) {
            if (empty($validated['preferred_date']) || empty($validated['preferred_period'])) {
                throw ValidationException::withMessages([
                    'preferred_option_id' => 'Vyberte dostupnú možnosť.',
                ]);
            }

            $availableOptions = $this->getAvailableRequestOptions(
                branch: $branch,
                selectedServices: $services,
                fromDate: Carbon::parse($validated['preferred_date']),
            );

            $selectedOptionId = $validated['preferred_option_id'] ?? null;

            if (! $selectedOptionId || ! $availableOptions->contains('id', $selectedOptionId)) {
                throw ValidationException::withMessages([
                    'preferred_option_id' => 'Táto možnosť už nie je dostupná.',
                ]);
            }
        }

        if ($requestType === 'appointment_request' && empty($validated['preferred_date'])) {
            throw ValidationException::withMessages([
                'preferred_date' => 'Vyberte preferovaný dátum.',
            ]);
        }

        $preferredDate = ! empty($validated['preferred_date'])
            ? Carbon::parse($validated['preferred_date'])->toDateString()
            : null;

        $preferredPeriod = $requestType === 'appointment_request'
            ? ($validated['preferred_period'] ?? null)
            : null;

        $requestedStartsAt = ! empty($validated['requested_starts_at'])
            ? Carbon::parse($validated['requested_starts_at'])
            : (! empty($validated['preferred_starts_at']) ? Carbon::parse($validated['preferred_starts_at']) : null);

        $requestedEndsAt = ! empty($validated['requested_ends_at'])
            ? Carbon::parse($validated['requested_ends_at'])
            : ($requestedStartsAt ? $requestedStartsAt->copy()->addMinutes(max(15, $totalDurationMinutes)) : null);

        $appointmentRequest = DB::transaction(function () use (
            $branch,
            $validated,
            $services,
            $totalDurationMinutes,
            $requestType,
            $preferredDate,
            $preferredPeriod,
            $requestedStartsAt,
            $requestedEndsAt,
        ): AppointmentRequest {
            $appointmentRequest = AppointmentRequest::create([
                'branch_id' => $branch->id,
                'service_id' => (int) ($validated['service_id'] ?? ($services->first()?->id ?? 0)) ?: null,
                'group_event_id' => (int) ($validated['group_event_id'] ?? 0) ?: null,
                'source_type' => $validated['source_type'] ?? null,
                'reservation_rule_id' => (int) ($validated['reservation_rule_id'] ?? 0) ?: null,
                'group_event_occurrence_original_start_at' => $validated['group_event_occurrence_original_start_at'] ?? null,
                'requested_starts_at' => $requestedStartsAt,
                'requested_ends_at' => $requestedEndsAt,
                'requested_group_event_starts_at' => $validated['requested_group_event_starts_at'] ?? null,
                'requested_group_event_ends_at' => $validated['requested_group_event_ends_at'] ?? null,
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'is_for_someone_else' => (bool) ($validated['is_for_someone_else'] ?? false),
                'requester_name' => $validated['requester_name'] ?? null,
                'requester_email' => $validated['requester_email'] ?? null,
                'requester_phone' => $validated['requester_phone'] ?? null,
                'preferred_date' => $preferredDate,
                'preferred_period' => $preferredPeriod,
                'preferred_starts_at' => $validated['preferred_starts_at'] ?? null,
                'preferred_time_note' => $validated['preferred_time_note'] ?? null,
                'total_duration_minutes' => $totalDurationMinutes,
                'patient_name' => $validated['patient_name'],
                'patient_email' => $validated['patient_email'] ?? null,
                'normalized_email' => $validated['requester_email'] ?? ($validated['patient_email'] ?? null),
                'patient_phone' => $validated['patient_phone'] ?? null,
                'normalized_phone' => $validated['patient_phone'] ?? null,
                'patient_birth_number' => $validated['patient_birth_number'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'patient_note' => $validated['patient_note'] ?? ($validated['message'] ?? null),
                'privacy_consent_accepted_at' => now(),
                'status' => AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION,
                'request_type' => $requestType,
            ]);

            foreach ($services as $service) {
                $appointmentRequest->services()->attach($service->id, [
                    'duration_minutes_snapshot' => (int) ($service->duration_minutes ?? 0),
                    'price_snapshot' => $service->self_pay_amount ?? null,
                ]);
            }

            return $appointmentRequest;
        });

        $appointmentRequest->load(['branch', 'services']);

        $verificationToken = $verificationService->issueToken($appointmentRequest);

        $verificationUrl = route('public.branch.booking.request.verify', [
            'branch' => $branch->slug,
            'appointmentRequest' => $appointmentRequest->id,
            'token' => $verificationToken,
        ]);

        $verificationEmailFailed = false;

        try {
            $emailNotificationService->dispatch('request.verification', [
                'appointment_request' => $appointmentRequest,
                'verification_url' => $verificationUrl,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            $verificationEmailFailed = true;
        }

        return redirect()
            ->route('public.branch.booking', ['branch' => $branch->slug])
            ->with('success', $verificationEmailFailed
                ? 'Požiadavka bola odoslaná. Overovací email sa nepodarilo odoslať, skúste to prosím znova neskôr.'
                : 'Požiadavka bola odoslaná. Prosím potvrďte ju cez odkaz v emaile.');
    }

    private function normalizePublicBookingMode(string $mode): string
    {
        return match ($mode) {
            'verified_patients_only' => 'verified_patients_direct',
            default => $mode,
        };
    }

    private function bookingSettings(Branch $branch): array
    {
        $settings = array_merge([
            'is_enabled' => false,
            'allow_service_selection' => true,
            'allow_appointment_requests' => true,
            'booking_mode' => 'requests_only',
            'intro_text' => null,
            'success_message' => null,
        ], $branch->booking_settings ?? []);

        $settings['booking_mode'] = $this->normalizePublicBookingMode((string) ($settings['booking_mode'] ?? 'requests_only'));

        return $settings;
    }

    private function notificationSettings(Branch $branch): array
    {
        return array_merge([
            'is_enabled' => false,
            'notification_emails' => [],
            'notify_new_appointment_request' => true,
            'notify_new_booking' => true,
            'notify_new_contact_form' => true,
        ], $branch->notification_settings ?? []);
    }

    private function ensurePublicSiteIsEnabled(Branch $branch): void
    {
        $branch->loadMissing('publicSite');

        abort_unless($branch->publicSite?->is_enabled, 404);
    }

    private function ensureBranchBookingIsEnabled(Branch $branch): void
    {
        abort_unless(
            $branch->booking_settings['is_enabled'] ?? false,
            404,
        );
    }

    private function ensureServiceBelongsToBranch(Branch $branch, Service $service): void
    {
        abort_unless(
            $branch->services()->whereKey($service->id)->exists(),
            404,
        );
    }

    private function templateView(Branch $branch, string $page): string
    {
        $branch->loadMissing('publicSite');

        $template = $branch->publicSite?->template ?? 'default';

        return "PublicBranch/Templates/{$template}/{$page}";
    }

    private function branchData(Branch $branch): array
    {
        $branch->loadMissing([
            'company',
            'publicSite',
            'contacts',
            'openingHours.intervals',
            'employees',
        ]);

        $insuranceCompanies = $this->resolveContractedInsuranceCompanies($branch);
        $otherCompanyBranches = $this->resolveOtherCompanyBranches($branch);

        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'slug' => $branch->slug,
            'type' => $branch->type,
            'description' => $branch->description,
            'website' => $branch->website,
            'address' => [
                'line_1' => $branch->address_line_1,
                'line_2' => $branch->address_line_2,
                'city' => $branch->city,
                'postal_code' => $branch->postal_code,
                'country' => $branch->country,
            ],
            'location' => [
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
            ],
            'company' => $branch->company ? [
                'id' => $branch->company->id,
                'name' => $branch->company->legal_name,
                'slug' => $branch->company->slug,
                'ico' => $branch->company->company_id_number,
                'dic' => $branch->company->tax_id,
                'ic_dph' => $branch->company->vat_id,
                'company_id_number' => $branch->company->company_id_number,
                'tax_id' => $branch->company->tax_id,
                'vat_id' => $branch->company->vat_id,
                'email' => $branch->company->email,
                'phone' => $branch->company->phone,
                'website' => $branch->company->website,
            ] : null,
            'public_site' => $branch->publicSite ? [
                'is_enabled' => $branch->publicSite->is_enabled,
                'template' => $branch->publicSite->template,
                'custom_domain' => $branch->publicSite->custom_domain,
                'primary_color' => $branch->publicSite->primary_color,
                'secondary_color' => $branch->publicSite->secondary_color,
                'logo_path' => $branch->publicSite->logo_path,
                'meta_title' => $branch->publicSite->meta_title,
                'meta_description' => $branch->publicSite->meta_description,
                'faq_items' => $branch->publicSite->faq_items ?? [],
            ] : null,
            'booking_settings' => $this->bookingSettings($branch),
            'notification_settings' => $this->notificationSettings($branch),
            'contacts' => $branch->contacts->map(fn ($contact) => [
                'type' => $contact->type,
                'label' => $contact->label,
                'value' => $contact->value,
                'is_primary' => $contact->is_primary,
            ])->values(),
            'opening_hours' => $branch->openingHours->map(fn ($openingHour) => [
                'day_of_week' => $openingHour->day_of_week,
                'is_closed' => $openingHour->is_closed,
                'note' => $openingHour->note,
                'intervals' => $openingHour->intervals->map(fn ($interval) => [
                    'opens_at' => $interval->opens_at,
                    'closes_at' => $interval->closes_at,
                ])->values(),
            ])->values(),
            'employees' => $branch->employees->map(fn ($employee) => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'title_before' => $employee->title_before,
                'title_after' => $employee->title_after,
                'position' => $employee->position,
                'bio' => $employee->bio,
                'photo_url' => $employee->photo_url,
                'email' => $employee->email,
                'phone' => $employee->phone,
            ])->values(),
            'contracted_insurance_companies' => $insuranceCompanies,
            'show_other_branches_in_footer' => (bool) $branch->show_other_branches_in_footer,
            'other_company_branches' => $otherCompanyBranches,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, full_name: string}>
     */
    private function resolveContractedInsuranceCompanies(Branch $branch): array
    {
        $catalog = config('health_insurance.companies', []);
        $selectedKeys = collect($branch->contracted_insurance_companies ?? [])
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->unique()
            ->values();

        return $selectedKeys
            ->map(function (string $key) use ($catalog): ?array {
                $item = $catalog[$key] ?? null;

                if (! is_array($item)) {
                    return null;
                }

                return [
                    'key' => $key,
                    'label' => (string) ($item['label'] ?? $key),
                    'full_name' => (string) ($item['full_name'] ?? ($item['label'] ?? $key)),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string, city: ?string, address_line_1: ?string, href: string}>
     */
    private function resolveOtherCompanyBranches(Branch $branch): array
    {
        if (! $branch->show_other_branches_in_footer) {
            return [];
        }

        return Branch::query()
            ->where('company_id', $branch->company_id)
            ->where('is_active', true)
            ->whereKeyNot($branch->id)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->whereHas('publicSite', fn ($query) => $query->where('is_enabled', true))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'city', 'address_line_1'])
            ->map(fn (Branch $otherBranch): array => [
                'id' => (int) $otherBranch->id,
                'name' => $otherBranch->name,
                'slug' => $otherBranch->slug,
                'city' => $otherBranch->city,
                'address_line_1' => $otherBranch->address_line_1,
                'href' => route('public.branch.home', $otherBranch->slug),
            ])
            ->values()
            ->all();
    }

    private function serviceCardData(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'short_description' => $service->short_description,
            'description' => $service->description,
            'icon' => $service->icon,
            'duration_sessions' => $service->duration_sessions,
            'duration_minutes' => $service->duration_minutes,
            'capacity' => $service->capacity,
            'is_bookable' => $service->is_bookable,
            'booking_type' => $service->booking_type,
            'public_booking_type' => $service->public_booking_type ?? 'appointment_request',
            'insurance_amount' => $service->insurance_amount,
            'self_pay_amount' => $service->self_pay_amount,
            'category' => $service->category ? [
                'id' => $service->category->id,
                'name' => $service->category->name,
                'slug' => $service->category->slug,
            ] : null,
        ];
    }

    private function serviceData(Service $service): array
    {
        return [
            ...$this->serviceCardData($service),
            'description' => $service->description,
            'insurance_note' => $service->insurance_note,
            'self_pay_note' => $service->self_pay_note,
            'information' => $service->information->map(fn ($item) => [
                'text' => $item->text,
            ])->values(),
            'necessities' => $service->necessities->map(fn ($item) => [
                'text' => $item->text,
            ])->values(),
            'steps' => $service->steps->map(fn ($step) => [
                'number' => $step->number,
                'title' => $step->title,
                'text' => $step->text,
            ])->values(),
            'tags' => $service->tags->map(fn ($tag) => [
                'name' => $tag->name,
            ])->values(),
            'files' => $service->files->map(fn ($file) => [
                'label' => $file->label,
                'original_name' => $file->original_name,
                'file_path' => $file->file_path,
            ])->values(),
        ];
    }
}