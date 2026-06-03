<?php

namespace App\Http\Controllers;

use App\Actions\CreateBookingAction;
use App\Models\AppointmentRequest;
use App\Models\Booking;
use App\Models\BookingAvailabilityRule;
use App\Models\BookingSlot;
use App\Models\Branch;
use App\Models\BranchInboxMessage;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
                ->take(4)
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

    public function booking(Request $request, Branch $branch): Response
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

        $selectedDate = Carbon::parse($request->string('date', now()->toDateString()));
        $selectedServiceIds = $this->normalizeSelectedServiceIds($request);

        $bookableServices = $branch->services
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->values();

        if (empty($selectedServiceIds) && $bookableServices->isNotEmpty()) {
            $selectedServiceIds = [
                (int) $bookableServices->first()->id,
            ];
        }

        $selectedServices = $bookableServices
            ->whereIn('id', $selectedServiceIds)
            ->values();

        $availableSlots = collect();
        $availableOptions = collect();

        if (
            $selectedServices->count() === 1
            && ($selectedServices->first()->booking_type ?? 'individual') === 'group'
        ) {
            $availableSlots = $this->getUpcomingExactGroupSlots(
                branch: $branch,
                service: $selectedServices->first(),
                fromDate: $selectedDate,
            );
        }

        if ($selectedServices->isNotEmpty()) {
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
            'availableSlots' => $availableSlots
                ->map(fn (BookingSlot $slot) => [
                    'id' => $slot->id,
                    'service_id' => $slot->service_id,
                    'service_name' => $slot->service?->name,
                    'starts_at' => $slot->starts_at->toDateTimeString(),
                    'ends_at' => $slot->ends_at->toDateTimeString(),
                    'capacity' => (int) $slot->capacity,
                    'confirmed_bookings_count' => (int) $slot->confirmed_bookings_count,
                    'free_capacity' => max(0, (int) $slot->capacity - (int) $slot->confirmed_bookings_count),
                ])
                ->values(),
            'availableOptions' => $availableOptions->values(),
        ]);
    }

    public function storeBooking(Request $request, Branch $branch, CreateBookingAction $createBookingAction): RedirectResponse
    {
        $this->ensurePublicSiteIsEnabled($branch);

        $validated = $request->validate([
            'mode' => ['required', 'in:exact_slot,appointment_request'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'booking_slot_id' => ['nullable', 'integer', 'exists:booking_slots,id'],
            'preferred_option_id' => ['nullable', 'string'],
            'preferred_date' => ['nullable', 'date'],
            'preferred_period' => ['nullable', 'string', 'in:morning,forenoon,afternoon,evening'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_note' => ['nullable', 'string'],
        ]);

        if ($validated['mode'] === 'exact_slot') {
            return $this->storeExactSlotBooking($branch, $validated, $createBookingAction);
        }

        return $this->storeAppointmentRequest($branch, $validated);
    }

    public function storeContactMessage(Request $request, Branch $branch): RedirectResponse
    {
        $this->ensurePublicSiteIsEnabled($branch);

        $validated = $request->validate([
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_email' => ['nullable', 'email', 'max:255'],
            'sender_phone' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        BranchInboxMessage::create([
            'branch_id' => $branch->id,
            'type' => 'contact_message',
            'title' => 'Nová správa z kontaktného formulára',
            'body' => $validated['body'],
            'sender_name' => $validated['sender_name'],
            'sender_email' => $validated['sender_email'] ?? null,
            'sender_phone' => $validated['sender_phone'] ?? null,
        ]);

        return back()->with('success', 'Správa bola odoslaná.');
    }

    private function normalizeSelectedServiceIds(Request $request): array
    {
        $services = $request->input('services', []);

        if (empty($services) && $request->filled('service')) {
            $services = [
                $request->input('service'),
            ];
        }

        if (! is_array($services)) {
            $services = [
                $services,
            ];
        }

        return collect($services)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function getUpcomingExactGroupSlots(Branch $branch, Service $service, Carbon $fromDate): Collection
    {
        if (($service->booking_type ?? 'individual') !== 'group') {
            return collect();
        }

        $start = $fromDate->copy()->startOfDay();

        if ($start->isPast()) {
            $start = now();
        }

        $end = $start->copy()->addDays(60);

        return BookingSlot::query()
            ->where('branch_id', $branch->id)
            ->where('service_id', $service->id)
            ->where('is_enabled', true)
            ->where('starts_at', '>=', $start)
            ->where('starts_at', '<=', $end)
            ->with('service')
            ->withCount([
                'bookings as confirmed_bookings_count' => function ($query) {
                    $query->whereNotIn('status', ['cancelled', 'rejected', 'no_show']);
                },
            ])
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (BookingSlot $slot) => $this->groupSlotCanBeShownToPatient($slot))
            ->values()
            ->take(30);
    }

    private function groupSlotCanBeShownToPatient(BookingSlot $slot): bool
    {
        $slot->loadMissing('service');

        $service = $slot->service;

        if (! $service) {
            return false;
        }

        if (($service->booking_type ?? 'individual') !== 'group') {
            return false;
        }

        if (! $slot->is_enabled) {
            return false;
        }

        if ($slot->starts_at->isPast()) {
            return false;
        }

        if (! $service->is_active || ! $service->is_bookable) {
            return false;
        }

        $sameSlotBookingsCount = Booking::query()
            ->where('branch_id', $slot->branch_id)
            ->where('booking_slot_id', $slot->id)
            ->where('service_id', $slot->service_id)
            ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
            ->count();

        $capacity = max(1, (int) ($slot->capacity ?? $service->capacity ?? 1));

        return $sameSlotBookingsCount < $capacity;
    }

    private function getAvailableRequestOptions(Branch $branch, Collection $selectedServices, Carbon $fromDate): Collection
    {
        $selectedServiceIds = $selectedServices
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $totalDurationMinutes = $selectedServices->sum(function (Service $service) {
            return (int) ($service->duration_minutes ?? 0);
        });

        if ($totalDurationMinutes <= 0) {
            return collect();
        }

        $rules = BookingAvailabilityRule::query()
            ->where('branch_id', $branch->id)
            ->where('is_enabled', true)
            ->where('slot_mode', 'free_bookable_time')
            ->with('services')
            ->get()
            ->filter(function (BookingAvailabilityRule $rule) use ($selectedServiceIds) {
                $ruleServiceIds = $rule->services
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);

                return $selectedServiceIds->every(fn ($id) => $ruleServiceIds->contains($id));
            });

        if ($rules->isEmpty()) {
            return collect();
        }

        $options = collect();
        $startDate = $fromDate->copy()->startOfDay();

        if ($startDate->isPast()) {
            $startDate = now()->startOfDay();
        }

        $endDate = $startDate->copy()->addDays(30);

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            foreach ($rules as $rule) {
                if (! $this->ruleAppliesOnDate($rule, $date)) {
                    continue;
                }

                $ruleStart = Carbon::parse($date->toDateString() . ' ' . $rule->starts_at);
                $ruleEnd = Carbon::parse($date->toDateString() . ' ' . $rule->ends_at);

                foreach ($this->periodsForDate($date) as $periodKey => $period) {
                    $periodStart = $period['starts_at'];
                    $periodEnd = $period['ends_at'];

                    $overlapStart = $ruleStart->greaterThan($periodStart)
                        ? $ruleStart->copy()
                        : $periodStart->copy();

                    $overlapEnd = $ruleEnd->lessThan($periodEnd)
                        ? $ruleEnd->copy()
                        : $periodEnd->copy();

                    if ($overlapStart->gte($overlapEnd)) {
                        continue;
                    }

                    if ($overlapEnd->isPast()) {
                        continue;
                    }

                    $periodCapacityMinutes = $overlapStart->diffInMinutes($overlapEnd);

                    $onlineCapacityMinutes = (int) floor($periodCapacityMinutes * 0.8);

                    $usedMinutes = $this->usedBookingMinutes($branch, $overlapStart, $overlapEnd);
                    $pendingRequestMinutes = $this->pendingRequestMinutes($branch, $date, $periodKey);

                    $remainingMinutes = $onlineCapacityMinutes - $usedMinutes - $pendingRequestMinutes;

                    if ($remainingMinutes < $totalDurationMinutes) {
                        continue;
                    }

                    $options->push([
                        'id' => $date->toDateString() . '_' . $periodKey,
                        'date' => $date->toDateString(),
                        'date_label' => $date->translatedFormat('l j.n.Y'),
                        'period' => $periodKey,
                        'period_label' => $period['label'],
                        'remaining_minutes' => $remainingMinutes,
                    ]);
                }
            }
        }

        return $options
            ->unique('id')
            ->values()
            ->take(20);
    }

    private function ruleAppliesOnDate(BookingAvailabilityRule $rule, Carbon $date): bool
    {
        $ruleDate = Carbon::parse($rule->date)->startOfDay();
        $targetDate = $date->copy()->startOfDay();

        if ($targetDate->lt($ruleDate)) {
            return false;
        }

        if (in_array($targetDate->toDateString(), $rule->excluded_dates ?? [], true)) {
            return false;
        }

        if (! empty($rule->repeat_ends_on) && $targetDate->gt(Carbon::parse($rule->repeat_ends_on)->endOfDay())) {
            return false;
        }

        if (! $rule->repeats) {
            return $targetDate->isSameDay($ruleDate);
        }

        $repeatEvery = max(1, (int) ($rule->repeat_every ?? 1));

        return match ($rule->repeat_unit) {
            'days' => $ruleDate->diffInDays($targetDate) % $repeatEvery === 0,
            'weeks' => $ruleDate->dayOfWeekIso === $targetDate->dayOfWeekIso
                && $ruleDate->diffInWeeks($targetDate) % $repeatEvery === 0,
            'months' => (int) $ruleDate->day === (int) $targetDate->day
                && $ruleDate->diffInMonths($targetDate) % $repeatEvery === 0,
            default => false,
        };
    }

    private function periodsForDate(Carbon $date): array
    {
        return [
            'morning' => [
                'label' => 'Ráno',
                'starts_at' => Carbon::parse($date->toDateString() . ' 06:00'),
                'ends_at' => Carbon::parse($date->toDateString() . ' 10:00'),
            ],
            'forenoon' => [
                'label' => 'Dopoludnia',
                'starts_at' => Carbon::parse($date->toDateString() . ' 10:00'),
                'ends_at' => Carbon::parse($date->toDateString() . ' 12:00'),
            ],
            'afternoon' => [
                'label' => 'Popoludní',
                'starts_at' => Carbon::parse($date->toDateString() . ' 12:00'),
                'ends_at' => Carbon::parse($date->toDateString() . ' 17:00'),
            ],
            'evening' => [
                'label' => 'Večer',
                'starts_at' => Carbon::parse($date->toDateString() . ' 17:00'),
                'ends_at' => Carbon::parse($date->toDateString() . ' 21:00'),
            ],
        ];
    }

    private function usedBookingMinutes(Branch $branch, Carbon $periodStart, Carbon $periodEnd): int
    {
        return Booking::query()
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
            ->whereHas('bookingSlot', function ($query) use ($periodStart, $periodEnd) {
                $query
                    ->where('starts_at', '<', $periodEnd)
                    ->where('ends_at', '>', $periodStart);
            })
            ->with('bookingSlot')
            ->get()
            ->sum(function (Booking $booking) use ($periodStart, $periodEnd) {
                if (! $booking->bookingSlot) {
                    return 0;
                }

                $slotStart = $booking->bookingSlot->starts_at;
                $slotEnd = $booking->bookingSlot->ends_at;

                $overlapStart = $slotStart->greaterThan($periodStart)
                    ? $slotStart
                    : $periodStart;

                $overlapEnd = $slotEnd->lessThan($periodEnd)
                    ? $slotEnd
                    : $periodEnd;

                if ($overlapStart->gte($overlapEnd)) {
                    return 0;
                }

                return $overlapStart->diffInMinutes($overlapEnd);
            });
    }

    private function pendingRequestMinutes(Branch $branch, Carbon $date, string $period): int
    {
        return (int) AppointmentRequest::query()
            ->where('branch_id', $branch->id)
            ->whereDate('preferred_date', $date->toDateString())
            ->where('preferred_period', $period)
            ->where('status', 'pending')
            ->sum('total_duration_minutes');
    }

    private function storeExactSlotBooking(
        Branch $branch,
        array $validated,
        CreateBookingAction $createBookingAction,
    ): RedirectResponse {
        if (empty($validated['booking_slot_id'])) {
            throw ValidationException::withMessages([
                'booking_slot_id' => 'Vyberte termín.',
            ]);
        }

        $slot = $branch->bookingSlots()
            ->with('service')
            ->whereKey($validated['booking_slot_id'])
            ->where('is_enabled', true)
            ->firstOrFail();

        if (($slot->service?->booking_type ?? 'individual') !== 'group') {
            throw ValidationException::withMessages([
                'booking_slot_id' => 'Tento termín nie je skupinový termín dostupný na priamu rezerváciu.',
            ]);
        }

        if (! in_array((int) $slot->service_id, collect($validated['service_ids'])->map(fn ($id) => (int) $id)->all(), true)) {
            throw ValidationException::withMessages([
                'service_ids' => 'Vybraný termín nepatrí k vybranej službe.',
            ]);
        }

        if (! $this->groupSlotCanBeShownToPatient($slot)) {
            throw ValidationException::withMessages([
                'booking_slot_id' => 'Tento termín už nie je dostupný.',
            ]);
        }

        $createBookingAction->execute($branch, $slot, [
            'patient_name' => $validated['patient_name'],
            'patient_email' => $validated['patient_email'] ?? null,
            'patient_phone' => $validated['patient_phone'] ?? null,
            'patient_note' => $validated['patient_note'] ?? null,
        ]);

        return redirect()
            ->route('public.branch.booking', ['branch' => $branch->slug])
            ->with('success', 'Termín bol rezervovaný. Skontrolujte si email s potvrdením.');
    }

    private function storeAppointmentRequest(Branch $branch, array $validated): RedirectResponse
    {
        if (empty($validated['preferred_date']) || empty($validated['preferred_period'])) {
            throw ValidationException::withMessages([
                'preferred_option_id' => 'Vyberte dostupnú možnosť.',
            ]);
        }

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

        $availableOptions = $this->getAvailableRequestOptions(
            branch: $branch,
            selectedServices: $services,
            fromDate: Carbon::parse($validated['preferred_date']),
        );

        $selectedOptionId = $validated['preferred_date'] . '_' . $validated['preferred_period'];

        if (! $availableOptions->contains('id', $selectedOptionId)) {
            throw ValidationException::withMessages([
                'preferred_option_id' => 'Táto možnosť už nie je dostupná.',
            ]);
        }

        DB::transaction(function () use ($branch, $validated, $services, $totalDurationMinutes): void {
            $appointmentRequest = AppointmentRequest::create([
                'branch_id' => $branch->id,
                'preferred_date' => $validated['preferred_date'],
                'preferred_period' => $validated['preferred_period'],
                'total_duration_minutes' => $totalDurationMinutes,
                'patient_name' => $validated['patient_name'],
                'patient_email' => $validated['patient_email'] ?? null,
                'patient_phone' => $validated['patient_phone'] ?? null,
                'patient_note' => $validated['patient_note'] ?? null,
                'status' => 'pending',
            ]);

            foreach ($services as $service) {
                $appointmentRequest->services()->attach($service->id, [
                    'duration_minutes_snapshot' => (int) ($service->duration_minutes ?? 0),
                    'price_snapshot' => $service->self_pay_amount ?? null,
                ]);
            }
        });

        return redirect()
            ->route('public.branch.booking', ['branch' => $branch->slug])
            ->with('success', 'Požiadavka bola odoslaná. Presný čas vám potvrdí sestra.');
    }

    private function ensureSlotCanBeBooked(BookingSlot $slot): void
    {
        if (! $this->slotCanBeShownToPatient($slot)) {
            throw ValidationException::withMessages([
                'booking_slot_id' => 'Tento termín už nie je dostupný.',
            ]);
        }
    }

    private function slotCanBeShownToPatient(BookingSlot $slot): bool
    {
        $slot->loadMissing('service');

        $service = $slot->service;

        if (! $service) {
            return false;
        }

        if (! $slot->is_enabled) {
            return false;
        }

        if ($slot->starts_at->isPast()) {
            return false;
        }

        if (! $service->is_active || ! $service->is_bookable) {
            return false;
        }

        $overlappingBookings = Booking::query()
            ->where('branch_id', $slot->branch_id)
            ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
            ->whereHas('bookingSlot', function ($query) use ($slot) {
                $query
                    ->where('starts_at', '<', $slot->ends_at)
                    ->where('ends_at', '>', $slot->starts_at);
            })
            ->get();

        if (($service->booking_type ?? 'individual') !== 'group') {
            return $overlappingBookings->isEmpty();
        }

        $blockingBookings = $overlappingBookings->filter(function (Booking $booking) use ($slot) {
            return (int) $booking->service_id !== (int) $slot->service_id
                || (int) $booking->booking_slot_id !== (int) $slot->id;
        });

        if ($blockingBookings->isNotEmpty()) {
            return false;
        }

        $sameSlotBookingsCount = $overlappingBookings
            ->filter(function (Booking $booking) use ($slot) {
                return (int) $booking->service_id === (int) $slot->service_id
                    && (int) $booking->booking_slot_id === (int) $slot->id;
            })
            ->count();

        $capacity = max(1, (int) ($slot->capacity ?? $service->capacity ?? 1));

        return $sameSlotBookingsCount < $capacity;
    }

    private function ensurePublicSiteIsEnabled(Branch $branch): void
    {
        $branch->loadMissing('publicSite');

        abort_unless($branch->publicSite?->is_enabled, 404);
    }

    private function ensureServiceBelongsToBranch(Branch $branch, Service $service): void
    {
        abort_unless(
            $branch->services()->whereKey($service->id)->exists(),
            404
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
                'template' => $branch->publicSite->template,
                'primary_color' => $branch->publicSite->primary_color,
                'secondary_color' => $branch->publicSite->secondary_color,
                'logo_path' => $branch->publicSite->logo_path,
                'meta_title' => $branch->publicSite->meta_title,
                'meta_description' => $branch->publicSite->meta_description,
                'faq_items' => $branch->publicSite->faq_items ?? [],
            ] : null,
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