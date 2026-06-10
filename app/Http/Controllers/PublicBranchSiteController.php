<?php

namespace App\Http\Controllers;

use App\Actions\CreateBookingAction;
use App\Events\BranchCalendarUpdated;
use App\Models\AppointmentRequest;
use App\Models\Booking;
use App\Models\BookingAvailabilityRule;
use App\Models\Branch;
use App\Models\CapacityWindow;
use App\Models\Service;
use App\Notifications\ContactFormSubmittedNotification;
use App\Notifications\RequestCreatedNotification;
use App\Services\BookingAvailabilityService;
use App\Services\BranchInboxMessageService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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
                ->map(fn (CapacityWindow $capacityWindow) => [
                    'id' => $capacityWindow->id,
                    'capacity_window_id' => $capacityWindow->id,
                    'service_id' => $capacityWindow->service_id,
                    'service_name' => $capacityWindow->service?->name,
                    'starts_at' => $capacityWindow->starts_at->toDateTimeString(),
                    'ends_at' => $capacityWindow->ends_at->toDateTimeString(),
                    'capacity' => (int) $capacityWindow->capacity,
                    'confirmed_bookings_count' => (int) $capacityWindow->confirmed_bookings_count,
                    'free_capacity' => max(
                        0,
                        (int) $capacityWindow->capacity - (int) $capacityWindow->confirmed_bookings_count,
                    ),
                ])
                ->values(),
            'availableOptions' => $availableOptions->values(),
            'availableOptionsPagination' => null,
            'bookingSettings' => $bookingSettings,
        ]);
    }

    public function storeBooking(
        Request $request,
        Branch $branch,
        CreateBookingAction $createBookingAction,
        BranchInboxMessageService $inboxMessageService,
    ): RedirectResponse {
        $this->ensurePublicSiteIsEnabled($branch);
        $this->ensureBranchBookingIsEnabled($branch);

        $bookingSettings = $this->bookingSettings($branch);

        $validated = $request->validate([
            'mode' => ['required', 'in:exact_slot,appointment_request'],
            'request_type' => ['nullable', 'string', 'in:preferred_period,general'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'capacity_window_id' => ['nullable', 'integer', 'exists:capacity_windows,id'],
            'preferred_option_id' => ['nullable', 'string'],
            'preferred_date' => ['nullable', 'date'],
            'preferred_period' => ['nullable', 'string', 'in:morning,forenoon,afternoon,evening'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['required', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_note' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:255'],
            'form_started_at' => ['nullable'],
        ]);

        if (! empty($validated['website'])) {
            return back()->with('success', 'Ďakujeme, vašu požiadavku sme prijali.');
        }

        if ($validated['mode'] === 'appointment_request' && ! $bookingSettings['allow_appointment_requests']) {
            throw ValidationException::withMessages([
                'mode' => 'Tento typ rezervácie nie je momentálne dostupný.',
            ]);
        }

        if ($validated['mode'] === 'exact_slot' && ! $bookingSettings['is_enabled']) {
            throw ValidationException::withMessages([
                'mode' => 'Priame rezervácie sú momentálne vypnuté.',
            ]);
        }

        if ($validated['mode'] === 'exact_slot') {
            return $this->storeExactCapacityWindowBooking(
                branch: $branch,
                validated: $validated,
                createBookingAction: $createBookingAction,
                inboxMessageService: $inboxMessageService,
            );
        }

        return $this->storeAppointmentRequest(
            branch: $branch,
            validated: $validated,
            inboxMessageService: $inboxMessageService,
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

        $rules = BookingAvailabilityRule::query()
            ->where('branch_id', $branch->id)
            ->where('is_enabled', true)
            ->where('slot_mode', 'free_bookable_time')
            ->with('services')
            ->get()
            ->filter(function (BookingAvailabilityRule $rule) use ($selectedServiceIds) {
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
        BookingAvailabilityRule $rule,
        Carbon $date,
        Carbon $periodStartsAt,
        Carbon $periodEndsAt,
    ): int {
        $ruleStartsAt = Carbon::parse($date->toDateString() . ' ' . $rule->starts_at);
        $ruleEndsAt = Carbon::parse($date->toDateString() . ' ' . $rule->ends_at);

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
        BookingAvailabilityRule $rule,
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

        if ($ruleServiceIds->isEmpty()) {
            $ruleServiceIds = collect($rule->service_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }

        if ($ruleServiceIds->isEmpty() && $rule->service_id) {
            $ruleServiceIds = collect([(int) $rule->service_id]);
        }

        return $selectedServiceIds
            ->diff($ruleServiceIds)
            ->isEmpty();
    }

    private function confirmedBookingMinutesInsideRange(
        Branch $branch,
        Carbon $startsAt,
        Carbon $endsAt,
    ): int {
        return Booking::query()
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->get()
            ->sum(function (Booking $booking) use ($startsAt, $endsAt) {
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
            ->where('status', 'pending')
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

    private function ruleAppliesOnDate(BookingAvailabilityRule $rule, Carbon $date): bool
    {
        $rawDate = $rule->date ?? $rule->starts_on ?? $rule->start_date;

        if (! $rawDate) {
            return false;
        }

        $ruleDate = Carbon::parse($rawDate)->startOfDay();
        $targetDate = $date->copy()->startOfDay();

        if ($targetDate->lt($ruleDate)) {
            return false;
        }

        $excludedDates = collect($rule->excluded_dates ?? [])
            ->map(fn ($excludedDate) => Carbon::parse($excludedDate)->toDateString())
            ->all();

        if (in_array($targetDate->toDateString(), $excludedDates, true)) {
            return false;
        }

        if ($rule->repeat_ends_on && $targetDate->gt(Carbon::parse($rule->repeat_ends_on)->startOfDay())) {
            return false;
        }

        if (! $rule->repeats) {
            return $targetDate->isSameDay($ruleDate);
        }

        $repeatEvery = max(1, (int) ($rule->repeat_every ?? $rule->repeat_interval ?? 1));
        $repeatUnit = $rule->repeat_unit ?? 'weeks';

        return match ($repeatUnit) {
            'days' => $ruleDate->diffInDays($targetDate) % $repeatEvery === 0,
            'months' => $ruleDate->diffInMonths($targetDate) % $repeatEvery === 0
                && (int) $ruleDate->day === (int) $targetDate->day,
            default => $ruleDate->dayOfWeekIso === $targetDate->dayOfWeekIso
                && $ruleDate->diffInWeeks($targetDate) % $repeatEvery === 0,
        };
    }

    private function storeExactCapacityWindowBooking(
        Branch $branch,
        array $validated,
        CreateBookingAction $createBookingAction,
        BranchInboxMessageService $inboxMessageService,
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

        $booking = DB::transaction(function () use (
            $branch,
            $validated,
            $selectedServiceIds,
            $createBookingAction,
        ): Booking {
            $capacityWindow = CapacityWindow::query()
                ->with('service')
                ->where('branch_id', $branch->id)
                ->whereKey($validated['capacity_window_id'])
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            if (! $capacityWindow->service || ! $capacityWindow->service->is_active || ! $capacityWindow->service->is_bookable) {
                throw ValidationException::withMessages([
                    'capacity_window_id' => 'Tento termín nie je dostupný na priamu rezerváciu.',
                ]);
            }

            if ($capacityWindow->starts_at->isPast()) {
                throw ValidationException::withMessages([
                    'capacity_window_id' => 'Tento termín už nie je dostupný.',
                ]);
            }

            if ((int) $capacityWindow->service_id !== (int) $selectedServiceIds->first()) {
                throw ValidationException::withMessages([
                    'service_ids' => 'Vybraný termín nepatrí k vybranej službe.',
                ]);
            }

            $confirmedBookingsCount = $capacityWindow
                ->bookings()
                ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
                ->lockForUpdate()
                ->count();

            if ($confirmedBookingsCount >= (int) $capacityWindow->capacity) {
                throw ValidationException::withMessages([
                    'capacity_window_id' => 'Tento termín je už obsadený.',
                ]);
            }

            return $createBookingAction->execute($branch, [
                'capacity_window_id' => $capacityWindow->id,
                'service_id' => $capacityWindow->service_id,
                'service_ids' => [$capacityWindow->service_id],
                'starts_at' => $capacityWindow->starts_at,
                'ends_at' => $capacityWindow->ends_at,
                'patient_name' => $validated['patient_name'],
                'patient_email' => $validated['patient_email'],
                'patient_phone' => $validated['patient_phone'] ?? null,
                'patient_note' => $validated['patient_note'] ?? null,
                'status' => 'confirmed',
                'notify_patient' => true,
            ]);
        });

        $inboxMessageService->createForBooking($booking);

        BranchCalendarUpdated::dispatch(
            branchId: $booking->branch_id,
            action: 'booking_created',
            bookingId: $booking->id,
        );

        return redirect()
            ->route('public.branch.booking', ['branch' => $branch->slug])
            ->with('success', 'Termín bol rezervovaný. Skontrolujte si email s potvrdením.');
    }

    private function storeAppointmentRequest(
        Branch $branch,
        array $validated,
        BranchInboxMessageService $inboxMessageService,
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

        $requestType = $validated['request_type'] ?? null;

        if (! $requestType) {
            $requestType = ! empty($validated['preferred_date']) && ! empty($validated['preferred_period'])
                ? 'preferred_period'
                : 'general';
        }

        if ($requestType === 'preferred_period') {
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

        if ($requestType === 'general' && empty($validated['preferred_date'])) {
            throw ValidationException::withMessages([
                'preferred_date' => 'Vyberte preferovaný dátum.',
            ]);
        }

        $preferredDate = ! empty($validated['preferred_date'])
            ? Carbon::parse($validated['preferred_date'])->toDateString()
            : null;

        $preferredPeriod = $requestType === 'preferred_period'
            ? $validated['preferred_period']
            : null;

        $appointmentRequest = DB::transaction(function () use (
            $branch,
            $validated,
            $services,
            $totalDurationMinutes,
            $requestType,
            $preferredDate,
            $preferredPeriod,
        ): AppointmentRequest {
            $appointmentRequest = AppointmentRequest::create([
                'branch_id' => $branch->id,
                'preferred_date' => $preferredDate,
                'preferred_period' => $preferredPeriod,
                'total_duration_minutes' => $totalDurationMinutes,
                'patient_name' => $validated['patient_name'],
                'patient_email' => $validated['patient_email'],
                'patient_phone' => $validated['patient_phone'] ?? null,
                'patient_note' => $validated['patient_note'] ?? null,
                'status' => 'pending',
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

        $inboxMessageService->createForAppointmentRequest($appointmentRequest);

        if ($appointmentRequest->patient_email) {
            Notification::route('mail', $appointmentRequest->patient_email)
                ->notify(new RequestCreatedNotification($appointmentRequest));
        }

        BranchCalendarUpdated::dispatch(
            branchId: $appointmentRequest->branch_id,
            action: 'appointment_request_created',
            appointmentRequestId: $appointmentRequest->id,
        );

        return redirect()
            ->route('public.branch.booking', ['branch' => $branch->slug])
            ->with('success', 'Požiadavka bola odoslaná. Skontrolujte si email s potvrdením prijatia.');
    }

    private function bookingSettings(Branch $branch): array
    {
        return array_merge([
            'is_enabled' => false,
            'allow_service_selection' => true,
            'allow_appointment_requests' => true,
            'intro_text' => null,
            'success_message' => null,
        ], $branch->booking_settings ?? []);
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
        ];
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