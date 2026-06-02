<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAvailabilityRule;
use App\Models\BookingSlot;
use App\Models\Branch;
use App\Models\BranchInboxMessage;
use App\Models\Service;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingRescheduledNotification;
use App\Services\BookingSlotGenerator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BranchBookingController extends Controller
{
    public function index(Request $request, Branch $branch): Response
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

        $allBookings = Booking::query()
            ->with(['service', 'bookingSlot.service'])
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereHas('bookingSlot', function ($query) use ($rangeStart, $rangeEnd) {
                $query
                    ->where('starts_at', '<', $rangeEnd)
                    ->where('ends_at', '>', $rangeStart);
            })
            ->orderByDesc('created_at')
            ->get();

        $capacityRules = BookingAvailabilityRule::query()
            ->with(['services'])
            ->where('branch_id', $branch->id)
            ->where('slot_mode', 'single_service_many_clients')
            ->where('is_enabled', true)
            ->get();

        $mapBooking = fn (Booking $booking) => [
            'id' => $booking->id,
            'booking_slot_id' => $booking->booking_slot_id,
            'service_id' => $booking->service_id,
            'service_name' => $booking->service?->name ?? $booking->bookingSlot?->service?->name,
            'patient_name' => $booking->patient_name,
            'patient_email' => $booking->patient_email,
            'patient_phone' => $booking->patient_phone,
            'starts_at' => $booking->bookingSlot?->starts_at?->toDateTimeString(),
            'ends_at' => $booking->bookingSlot?->ends_at?->toDateTimeString(),
            'status' => $booking->status,
            'patient_note' => $booking->patient_note,
            'admin_note' => $booking->admin_note,
        ];

        $capacityBookingIds = collect();

        $calendarCapacityWindows = $capacityRules
            ->flatMap(function (BookingAvailabilityRule $rule) use ($rangeStart, $rangeEnd, $allBookings, $mapBooking, $capacityBookingIds) {
                $ruleDates = $this->getRuleDatesForRange($rule, $rangeStart, $rangeEnd);

                return collect($ruleDates)->map(function (Carbon $ruleDate) use ($rule, $allBookings, $mapBooking, $capacityBookingIds) {
                    $windowStart = Carbon::parse($ruleDate->toDateString() . ' ' . $rule->starts_at);
                    $windowEnd = Carbon::parse($ruleDate->toDateString() . ' ' . $rule->ends_at);

                    $serviceIds = $rule->services
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->values();

                    if ($serviceIds->isEmpty() && $rule->service_id) {
                        $serviceIds = collect([(int) $rule->service_id]);
                    }

                    $bookings = $allBookings
                        ->filter(function (Booking $booking) use ($serviceIds, $windowStart, $windowEnd) {
                            if (! $booking->bookingSlot) {
                                return false;
                            }

                            if (! $serviceIds->contains((int) $booking->service_id)) {
                                return false;
                            }

                            return $booking->bookingSlot->starts_at->lt($windowEnd)
                                && $booking->bookingSlot->ends_at->gt($windowStart);
                        })
                        ->values();

                    $bookings->each(function (Booking $booking) use ($capacityBookingIds) {
                        $capacityBookingIds->push($booking->id);
                    });

                    $service = $rule->services->first();

                    if (! $service && $rule->service_id) {
                        $service = Service::query()->find($rule->service_id);
                    }

                    return [
                        'id' => $rule->id . '-' . $ruleDate->toDateString(),
                        'rule_id' => $rule->id,
                        'service_id' => $rule->service_id,
                        'service_name' => $service?->name,
                        'date' => $ruleDate->toDateString(),
                        'starts_at' => $windowStart->toDateTimeString(),
                        'ends_at' => $windowEnd->toDateTimeString(),
                        'starts_time' => $windowStart->format('H:i'),
                        'ends_time' => $windowEnd->format('H:i'),
                        'capacity' => $rule->bookable_places,
                        'bookings' => $bookings
                            ->map($mapBooking)
                            ->values(),
                    ];
                });
            })
            ->values();

        $capacityBookingIds = $capacityBookingIds
            ->unique()
            ->values();

        $calendarBookings = $allBookings
            ->reject(fn (Booking $booking) => $capacityBookingIds->contains($booking->id))
            ->map($mapBooking)
            ->values();

        return Inertia::render('Admin/Branches/BookingSettings', [
            'branch' => $branch,
            'services' => Service::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'availableRescheduleSlots' => $this->availableAdminSlots($branch),
            'calendarBookings' => $calendarBookings,
            'calendarCapacityWindows' => $calendarCapacityWindows,
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
        ]);

        DB::transaction(function () use ($branch, $validated): void {
            foreach ($validated['services'] as $item) {
                Service::query()
                    ->where('branch_id', $branch->id)
                    ->whereKey($item['id'])
                    ->update([
                        'is_bookable' => $item['is_bookable'],
                        'duration_minutes' => $item['duration_minutes'] ?? null,
                        'capacity' => $item['capacity'] ?? 1,
                        'buffer_before_minutes' => $item['buffer_before_minutes'] ?? 0,
                        'buffer_after_minutes' => $item['buffer_after_minutes'] ?? 0,
                        'booking_type' => $item['booking_type'],
                    ]);
            }
        });

        return back()->with('success', 'Nastavenia služieb boli uložené.');
    }

    public function updateRules(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $validated = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.id' => ['nullable', 'integer', 'exists:booking_availability_rules,id'],
            'rules.*.date' => ['required', 'date'],
            'rules.*.starts_at' => ['required', 'date_format:H:i'],
            'rules.*.ends_at' => ['required', 'date_format:H:i'],
            'rules.*.slot_mode' => ['required', 'in:single_service_many_clients,free_bookable_time'],
            'rules.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'rules.*.service_ids' => ['nullable', 'array'],
            'rules.*.service_ids.*' => ['integer', 'exists:services,id'],
            'rules.*.bookable_places' => ['required', 'integer', 'min:1'],
            'rules.*.repeats' => ['required', 'boolean'],
            'rules.*.repeat_every' => ['required', 'integer', 'min:1'],
            'rules.*.repeat_unit' => ['required', 'in:days,weeks,months'],
            'rules.*.repeat_ends_on' => ['nullable', 'date'],
            'rules.*.excluded_dates' => ['nullable', 'array'],
            'rules.*.excluded_dates.*' => ['date'],
            'rules.*.is_enabled' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($branch, $validated): void {
            $keepIds = [];

            foreach ($validated['rules'] as $ruleData) {
                $existingRule = null;

                if (! empty($ruleData['id'])) {
                    $existingRule = BookingAvailabilityRule::query()
                        ->where('branch_id', $branch->id)
                        ->whereKey($ruleData['id'])
                        ->first();
                }

                $dayOfWeek = Carbon::parse($ruleData['date'])->dayOfWeekIso;

                $serviceIds = $ruleData['slot_mode'] === 'single_service_many_clients'
                    ? array_values(array_filter([$ruleData['service_id'] ?? null]))
                    : array_values($ruleData['service_ids'] ?? []);

                $rule = BookingAvailabilityRule::updateOrCreate(
                    [
                        'id' => $ruleData['id'] ?? null,
                        'branch_id' => $branch->id,
                    ],
                    [
                        'date' => $ruleData['date'],
                        'day_of_week' => $dayOfWeek,
                        'starts_at' => $ruleData['starts_at'],
                        'ends_at' => $ruleData['ends_at'],
                        'slot_mode' => $ruleData['slot_mode'],
                        'service_id' => $ruleData['slot_mode'] === 'single_service_many_clients'
                            ? ($ruleData['service_id'] ?? null)
                            : null,
                        'service_ids' => $serviceIds,
                        'bookable_places' => $ruleData['slot_mode'] === 'single_service_many_clients'
                            ? $ruleData['bookable_places']
                            : 1,
                        'repeats' => $ruleData['repeats'],
                        'repeat_every' => $ruleData['repeats'] ? $ruleData['repeat_every'] : 1,
                        'repeat_unit' => $ruleData['repeats'] ? $ruleData['repeat_unit'] : 'weeks',
                        'repeat_ends_on' => array_key_exists('repeat_ends_on', $ruleData)
                            ? $ruleData['repeat_ends_on']
                            : $existingRule?->repeat_ends_on,
                        'excluded_dates' => array_key_exists('excluded_dates', $ruleData)
                            ? ($ruleData['excluded_dates'] ?? [])
                            : ($existingRule?->excluded_dates ?? []),
                        'is_enabled' => $ruleData['is_enabled'],
                    ],
                );

                $rule->services()->sync($serviceIds);
                $keepIds[] = $rule->id;
            }

            BookingAvailabilityRule::query()
                ->where('branch_id', $branch->id)
                ->whereNotIn('id', $keepIds)
                ->delete();
        });

        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Pravidlá dostupnosti boli uložené.');
    }

    public function updateSlot(Request $request, Branch $branch, BookingSlot $slot): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($slot->branch_id !== $branch->id, 404);

        $validated = $request->validate([
            'capacity' => ['required', 'integer', 'min:1'],
            'is_enabled' => ['required', 'boolean'],
        ]);

        $slot->update($validated);

        return back()->with('success', 'Slot bol upravený.');
    }

    public function storeAdminBooking(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $validated = $request->validate([
            'booking_slot_id' => ['nullable', 'integer', 'exists:booking_slots,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_note' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
        ]);

        $slot = $this->resolveAdminSlot($branch, $validated);

        Booking::create([
            'booking_slot_id' => $slot->id,
            'branch_id' => $branch->id,
            'service_id' => $slot->service_id,
            'patient_name' => $validated['patient_name'],
            'patient_email' => $validated['patient_email'] ?? null,
            'patient_phone' => $validated['patient_phone'] ?? null,
            'patient_note' => $validated['patient_note'] ?? null,
            'admin_note' => $validated['admin_note'] ?? null,
            'status' => 'confirmed',
        ]);

        return back()->with('success', 'Rezervácia bola vytvorená.');
    }

    public function updateBooking(Request $request, Branch $branch, Booking $booking): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($booking->branch_id !== $branch->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,cancelled,completed,no_show'],
            'admin_note' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldStatus = $booking->status;

        $booking->update([
            'status' => $validated['status'],
            'admin_note' => $validated['admin_note'] ?? null,
        ]);

        if (
            $oldStatus !== 'cancelled'
            && $validated['status'] === 'cancelled'
            && ($validated['notify_patient'] ?? true)
        ) {
            $this->notifyBookingCancelled($booking, $validated['notification_reason'] ?? null);
        }

        return back()->with('success', 'Rezervácia bola upravená.');
    }

    public function cancelBooking(Request $request, Branch $branch, Booking $booking): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($booking->branch_id !== $branch->id, 404);

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking->update([
            'status' => 'cancelled',
            'admin_note' => $validated['admin_note'] ?? $booking->admin_note,
        ]);

        if ($validated['notify_patient'] ?? true) {
            $this->notifyBookingCancelled($booking, $validated['notification_reason'] ?? null);
        }

        return back()->with('success', 'Rezervácia bola zrušená.');
    }

    public function rescheduleBooking(Request $request, Branch $branch, Booking $booking): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($booking->branch_id !== $branch->id, 404);

        $validated = $request->validate([
            'booking_slot_id' => ['nullable', 'integer', 'exists:booking_slots,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'admin_note' => ['nullable', 'string'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldSlot = $booking->bookingSlot;
        $oldStartsAt = $oldSlot?->starts_at?->copy();
        $oldEndsAt = $oldSlot?->ends_at?->copy();

        $newSlot = $this->resolveAdminSlot($branch, [
            ...$validated,
            'service_id' => $validated['service_id'] ?? $booking->service_id,
        ]);

        $booking->update([
            'booking_slot_id' => $newSlot->id,
            'service_id' => $newSlot->service_id,
            'status' => 'confirmed',
            'admin_note' => $validated['admin_note'] ?? $booking->admin_note,
        ]);

        if ($validated['notify_patient'] ?? true) {
            $booking->refresh()->load(['branch', 'service', 'bookingSlot']);

            $this->notifyBookingRescheduled(
                booking: $booking,
                oldStartsAt: $oldStartsAt,
                oldEndsAt: $oldEndsAt,
                reason: $validated['notification_reason'] ?? null,
            );
        }

        return back()->with('success', 'Rezervácia bola presunutá.');
    }

    public function cancelCapacityWindow(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $bookings = $this->getCapacityWindowBookings(
            branch: $branch,
            rule: $rule,
            date: $date,
        );

        foreach ($bookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
            ]);

            if ($validated['notify_patient'] ?? true) {
                $this->notifyBookingCancelled($booking, $validated['notification_reason'] ?? null);
            }
        }

        $excludedDates = $rule->excluded_dates ?? [];
        $dateString = $date->toDateString();

        if (! in_array($dateString, $excludedDates, true)) {
            $excludedDates[] = $dateString;
        }

        sort($excludedDates);

        $rule->update([
            'excluded_dates' => $excludedDates,
        ]);

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleDate($rule, $date);
        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Kapacitné okno bolo zrušené.');
    }

    public function rescheduleCapacityWindow(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
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

        $bookings = $this->getCapacityWindowBookings(
            branch: $branch,
            rule: $rule,
            date: $oldDate,
        );

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

        $targetRule = $this->moveCapacityWindowRuleOccurrence(
            branch: $branch,
            rule: $rule,
            oldDate: $oldDate,
            newStartsAt: $newStartsAt,
            newEndsAt: $newEndsAt,
            serviceId: (int) $service->id,
        );

        $newSlot = BookingSlot::firstOrCreate(
            [
                'branch_id' => $branch->id,
                'service_id' => $service->id,
                'starts_at' => $newStartsAt,
                'ends_at' => $newEndsAt,
            ],
            [
                'capacity' => max(1, (int) ($targetRule->bookable_places ?? $service->capacity ?? 1)),
                'is_enabled' => true,
            ],
        );

        if (! $newSlot->is_enabled) {
            $newSlot->update([
                'capacity' => max(1, (int) ($targetRule->bookable_places ?? $service->capacity ?? 1)),
                'is_enabled' => true,
            ]);
        }

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

                $this->notifyBookingRescheduled(
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

    public function markMessageRead(Request $request, Branch $branch, BranchInboxMessage $message): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($message->branch_id !== $branch->id, 404);

        $message->update([
            'read_at' => now(),
        ]);

        return back();
    }

    public function regenerateSlots(Request $request, Branch $branch, BookingSlotGenerator $generator): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $generator->generateForBranch($branch->id, (int) $request->integer('days', 60));

        return back()->with('success', 'Sloty boli vygenerované.');
    }

    public function destroy(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $ruleStartDate = $rule->date
            ? Carbon::parse($rule->date)
            : now();

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleFromDate($rule, $ruleStartDate);

        $rule->delete();

        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Pravidlo bolo vymazané.');
    }

    public function excludeDate(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();

        $excludedDates = $rule->excluded_dates ?? [];

        if (! in_array($date, $excludedDates, true)) {
            $excludedDates[] = $date;
        }

        sort($excludedDates);

        $rule->update([
            'excluded_dates' => $excludedDates,
        ]);

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleDate($rule, Carbon::parse($date));
        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Tento deň bol vymazaný z opakovania.');
    }

    public function endBeforeDate(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $rule->update([
            'repeat_ends_on' => $date->copy()->subDay()->toDateString(),
        ]);

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleFromDate($rule, $date);
        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Opakovanie bolo ukončené.');
    }

    private function resolveAdminSlot(Branch $branch, array $data): BookingSlot
    {
        if (! empty($data['booking_slot_id'])) {
            $slot = BookingSlot::query()
                ->where('branch_id', $branch->id)
                ->whereKey($data['booking_slot_id'])
                ->firstOrFail();

            if (! $slot->is_enabled) {
                $slot->update([
                    'is_enabled' => true,
                ]);
            }

            return $slot;
        }

        if (empty($data['service_id']) || empty($data['starts_at']) || empty($data['ends_at'])) {
            throw ValidationException::withMessages([
                'starts_at' => 'Vyberte službu, začiatok a koniec rezervácie.',
            ]);
        }

        $service = Service::query()
            ->where('branch_id', $branch->id)
            ->whereKey($data['service_id'])
            ->firstOrFail();

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Koniec rezervácie musí byť po začiatku.',
            ]);
        }

        $slot = BookingSlot::firstOrCreate(
            [
                'branch_id' => $branch->id,
                'service_id' => $service->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ],
            [
                'capacity' => max(1, (int) ($service->capacity ?? 1)),
                'is_enabled' => true,
            ],
        );

        if (! $slot->is_enabled) {
            $slot->update([
                'is_enabled' => true,
            ]);
        }

        return $slot;
    }

    private function availableAdminSlots(Branch $branch): Collection
    {
        return BookingSlot::query()
            ->with('service')
            ->where('branch_id', $branch->id)
            ->where('starts_at', '>=', now())
            ->where('is_enabled', true)
            ->orderBy('starts_at')
            ->get()
            ->map(function (BookingSlot $slot) {
                return [
                    'id' => $slot->id,
                    'service_id' => $slot->service_id,
                    'service_name' => $slot->service?->name,
                    'starts_at' => $slot->starts_at->toDateTimeString(),
                    'ends_at' => $slot->ends_at->toDateTimeString(),
                    'label' => $slot->starts_at->format('d.m.Y H:i') . ' - ' . $slot->ends_at->format('H:i') . ' · ' . ($slot->service?->name ?? 'Služba'),
                ];
            })
            ->values();
    }

    private function getRuleDatesForRange(BookingAvailabilityRule $rule, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        if (! $rule->date) {
            return [];
        }

        $startDate = Carbon::parse($rule->date)->startOfDay();

        $excludedDates = collect($rule->excluded_dates ?? [])
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $repeatEndsOn = $rule->repeat_ends_on
            ? Carbon::parse($rule->repeat_ends_on)->endOfDay()
            : null;

        if ($repeatEndsOn && $repeatEndsOn->lt($rangeStart)) {
            return [];
        }

        $effectiveRangeEnd = $repeatEndsOn && $repeatEndsOn->lt($rangeEnd)
            ? $repeatEndsOn
            : $rangeEnd;

        if (! $rule->repeats) {
            $dateString = $startDate->toDateString();

            if (
                $startDate->betweenIncluded($rangeStart, $effectiveRangeEnd)
                && ! in_array($dateString, $excludedDates, true)
            ) {
                return [$startDate];
            }

            return [];
        }

        $dates = [];

        $repeatEvery = max((int) $rule->repeat_every, 1);
        $repeatUnit = $rule->repeat_unit ?? 'weeks';

        $cursor = $startDate->copy();

        while ($cursor->lt($rangeStart)) {
            match ($repeatUnit) {
                'days' => $cursor->addDays($repeatEvery),
                'months' => $cursor->addMonths($repeatEvery),
                default => $cursor->addWeeks($repeatEvery),
            };
        }

        while ($cursor->lte($effectiveRangeEnd)) {
            $dateString = $cursor->toDateString();

            if (! in_array($dateString, $excludedDates, true)) {
                $dates[] = $cursor->copy();
            }

            match ($repeatUnit) {
                'days' => $cursor->addDays($repeatEvery),
                'months' => $cursor->addMonths($repeatEvery),
                default => $cursor->addWeeks($repeatEvery),
            };
        }

        return $dates;
    }

    private function getCapacityWindowBookings(Branch $branch, BookingAvailabilityRule $rule, Carbon $date): Collection
    {
        $windowStart = Carbon::parse($date->toDateString() . ' ' . $rule->starts_at);
        $windowEnd = Carbon::parse($date->toDateString() . ' ' . $rule->ends_at);

        $serviceIds = $rule->services
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($serviceIds->isEmpty() && $rule->service_id) {
            $serviceIds = collect([(int) $rule->service_id]);
        }

        return Booking::query()
            ->with(['branch', 'service', 'bookingSlot'])
            ->where('branch_id', $branch->id)
            ->whereIn('service_id', $serviceIds)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereHas('bookingSlot', function ($query) use ($windowStart, $windowEnd) {
                $query
                    ->where('starts_at', '<', $windowEnd)
                    ->where('ends_at', '>', $windowStart);
            })
            ->get();
    }

    private function moveCapacityWindowRuleOccurrence(
        Branch $branch,
        BookingAvailabilityRule $rule,
        Carbon $oldDate,
        Carbon $newStartsAt,
        Carbon $newEndsAt,
        int $serviceId,
    ): BookingAvailabilityRule {
        $newDate = $newStartsAt->toDateString();

        if (! $rule->repeats) {
            $rule->update([
                'date' => $newDate,
                'day_of_week' => $newStartsAt->dayOfWeekIso,
                'starts_at' => $newStartsAt->format('H:i'),
                'ends_at' => $newEndsAt->format('H:i'),
            ]);

            return $rule->fresh(['services']);
        }

        $excludedDates = $rule->excluded_dates ?? [];
        $oldDateString = $oldDate->toDateString();

        if (! in_array($oldDateString, $excludedDates, true)) {
            $excludedDates[] = $oldDateString;
        }

        sort($excludedDates);

        $rule->update([
            'excluded_dates' => $excludedDates,
        ]);

        $newRule = BookingAvailabilityRule::create([
            'branch_id' => $branch->id,
            'day_of_week' => $newStartsAt->dayOfWeekIso,
            'date' => $newDate,
            'starts_at' => $newStartsAt->format('H:i'),
            'ends_at' => $newEndsAt->format('H:i'),
            'slot_mode' => 'single_service_many_clients',
            'service_id' => $serviceId,
            'service_ids' => [$serviceId],
            'bookable_places' => $rule->bookable_places,
            'repeats' => false,
            'repeat_every' => 1,
            'repeat_unit' => 'weeks',
            'repeat_ends_on' => null,
            'excluded_dates' => [],
            'is_enabled' => true,
        ]);

        $newRule->services()->sync([$serviceId]);

        return $newRule->fresh(['services']);
    }

    private function notifyBookingCancelled(Booking $booking, ?string $reason = null): void
    {
        $booking->loadMissing(['branch', 'service', 'bookingSlot']);

        if (! filled($booking->patient_email)) {
            return;
        }

        Notification::route('mail', $booking->patient_email)
            ->notify(new BookingCancelledNotification($booking, $reason));
    }

    private function notifyBookingRescheduled(
        Booking $booking,
        ?Carbon $oldStartsAt = null,
        ?Carbon $oldEndsAt = null,
        ?string $reason = null,
    ): void {
        $booking->loadMissing(['branch', 'service', 'bookingSlot']);

        if (! filled($booking->patient_email)) {
            return;
        }

        Notification::route('mail', $booking->patient_email)
            ->notify(new BookingRescheduledNotification(
                booking: $booking,
                oldStartsAt: $oldStartsAt,
                oldEndsAt: $oldEndsAt,
                reason: $reason,
            ));
    }

    public function deleteCapacityWindowOccurrence(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $bookings = $this->getCapacityWindowBookings(
            branch: $branch,
            rule: $rule,
            date: $date,
        );

        foreach ($bookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
            ]);

            if ($validated['notify_patient'] ?? true) {
                $this->notifyBookingCancelled($booking, $validated['notification_reason'] ?? null);
            }
        }

        $excludedDates = $rule->excluded_dates ?? [];
        $dateString = $date->toDateString();

        if (! in_array($dateString, $excludedDates, true)) {
            $excludedDates[] = $dateString;
        }

        sort($excludedDates);

        $rule->update([
            'excluded_dates' => $excludedDates,
        ]);

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleDate($rule, $date);
        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Tento skupinový termín bol vymazaný.');
    }

    public function deleteCapacityWindowFromDate(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_unless((int) $rule->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'notify_patient' => ['nullable', 'boolean'],
            'notification_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $bookings = $this->getCapacityWindowBookingsFromDate(
            branch: $branch,
            rule: $rule,
            date: $date,
        );

        foreach ($bookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
            ]);

            if ($validated['notify_patient'] ?? true) {
                $this->notifyBookingCancelled($booking, $validated['notification_reason'] ?? null);
            }
        }

        $rule->update([
            'repeat_ends_on' => $date->copy()->subDay()->toDateString(),
        ]);

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleFromDate($rule, $date);
        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Budúce skupinové termíny boli vymazané.');
    }

    public function deleteCapacityWindowSeries(Request $request, Branch $branch, BookingAvailabilityRule $rule): RedirectResponse
    {
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

        $bookings = $this->getCapacityWindowBookingsFromDate(
            branch: $branch,
            rule: $rule,
            date: $date,
        );

        foreach ($bookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
            ]);

            if ($validated['notify_patient'] ?? true) {
                $this->notifyBookingCancelled($booking, $validated['notification_reason'] ?? null);
            }
        }

        app(BookingSlotGenerator::class)->disableSlotsWithoutBookingsForRuleFromDate($rule, $date);

        $rule->delete();

        app(BookingSlotGenerator::class)->generateForBranch($branch->id, 60);

        return back()->with('success', 'Celá skupinová séria bola vymazaná.');
    }

    private function getCapacityWindowBookingsFromDate(Branch $branch, BookingAvailabilityRule $rule, Carbon $date): Collection
    {
        $serviceIds = $rule->services
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($serviceIds->isEmpty() && $rule->service_id) {
            $serviceIds = collect([(int) $rule->service_id]);
        }

        return Booking::query()
            ->with(['branch', 'service', 'bookingSlot'])
            ->where('branch_id', $branch->id)
            ->whereIn('service_id', $serviceIds)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereHas('bookingSlot', function ($query) use ($date) {
                $query->where('starts_at', '>=', $date);
            })
            ->get();
    }
}