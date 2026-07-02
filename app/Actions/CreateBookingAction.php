<?php

namespace App\Actions;

use App\Events\BranchCalendarUpdated;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CapacityWindow;
use App\Models\Service;
use App\Notifications\BookingCreatedNotification;
use App\Services\DisabledDayService;
use App\Services\PatientDirectoryService;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBookingAction
{
    public function __construct(
        private DisabledDayService $disabledDayService,
        private RecurrenceService $recurrenceService,
        private PatientDirectoryService $patientDirectoryService,
    )
    {
    }

    public function execute(Branch $branch, array $data): Booking
    {
        $booking = DB::transaction(function () use ($branch, $data): Booking {
            $capacityWindow = $this->resolveCapacityWindow($branch, $data);

            if ($capacityWindow) {
                $this->ensureCapacityWindowHasSpace($capacityWindow);
            }

            $services = $this->resolveServices($branch, $data, $capacityWindow);

            if ($services->isEmpty()) {
                throw ValidationException::withMessages([
                    'service_ids' => 'Vyberte aspoň jednu službu.',
                ]);
            }

            $primaryService = $this->resolvePrimaryService($services, $data);

            $startsAt = $capacityWindow
                ? $capacityWindow->starts_at->copy()
                : Carbon::parse($data['starts_at']);

            if ($this->disabledDayService->isDisabled($branch, $startsAt)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Tento deň je v kalendári zakázaný.',
                ]);
            }

            $endsAt = $capacityWindow
                ? $capacityWindow->ends_at->copy()
                : $this->resolveEndsAt($startsAt, $services, $data);

            $recurrence = ! empty($data['recurrence'])
                ? $this->recurrenceService->normalize($data['recurrence'])
                : null;

            $supportsRecurrenceColumns = Schema::hasColumn('bookings', 'series_uuid')
                && Schema::hasColumn('bookings', 'recurrence')
                && Schema::hasColumn('bookings', 'recurrence_excluded_dates');

            $seriesUuid = $supportsRecurrenceColumns && $recurrence
                ? (string) Str::uuid()
                : null;

            $bookingPayload = [
                'branch_id' => $branch->id,
                'service_id' => $primaryService->id,
                'capacity_window_id' => $capacityWindow?->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'patient_name' => $data['patient_name'],
                'patient_email' => $data['patient_email'] ?? null,
                'patient_phone' => $data['patient_phone'] ?? null,
                'status' => $data['status'] ?? 'confirmed',
                'patient_note' => $data['patient_note'] ?? null,
                'admin_note' => $data['admin_note'] ?? null,
            ];

            if (Schema::hasColumn('bookings', 'series_uuid')) {
                $bookingPayload['series_uuid'] = $seriesUuid;
            }

            if (Schema::hasColumn('bookings', 'recurrence')) {
                $bookingPayload['recurrence'] = $supportsRecurrenceColumns ? $recurrence : null;
            }

            if (Schema::hasColumn('bookings', 'recurrence_excluded_dates')) {
                $bookingPayload['recurrence_excluded_dates'] = [];
            }

            $booking = Booking::query()->create($bookingPayload);

            $this->syncBookingServices($booking, $services);

            return $booking;
        });

        $booking->load([
            'branch.contacts',
            'service',
            'services',
            'capacityWindow',
        ]);

        $this->patientDirectoryService->savePatient(
            branch: $branch,
            name: $booking->patient_name,
            email: $booking->patient_email,
            phone: $booking->patient_phone,
        );

        BranchCalendarUpdated::dispatch(
            branchId: $booking->branch_id,
            action: 'booking_created',
            bookingId: $booking->id,
        );

        if ($booking->patient_email && ($data['notify_patient'] ?? true)) {
            Notification::route('mail', $booking->patient_email)
                ->notify(new BookingCreatedNotification($booking));
        }

        return $booking;
    }

    private function resolveCapacityWindow(Branch $branch, array $data): ?CapacityWindow
    {
        if (empty($data['capacity_window_id'])) {
            return null;
        }

        return CapacityWindow::query()
            ->where('branch_id', $branch->id)
            ->whereKey($data['capacity_window_id'])
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureCapacityWindowHasSpace(CapacityWindow $capacityWindow): void
    {
        if ($capacityWindow->status !== 'active') {
            throw ValidationException::withMessages([
                'capacity_window_id' => 'Tento skupinový termín už nie je aktívny.',
            ]);
        }

        $activeBookingsCount = $capacityWindow->bookings()
            ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
            ->count();

        if ($activeBookingsCount >= $capacityWindow->capacity) {
            throw ValidationException::withMessages([
                'capacity_window_id' => 'Skupinový termín je už naplnený.',
            ]);
        }
    }

    private function resolveServices(
        Branch $branch,
        array $data,
        ?CapacityWindow $capacityWindow,
    ): Collection {
        $serviceIds = collect($data['service_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($serviceIds->isEmpty() && ! empty($data['service_id'])) {
            $serviceIds = collect([(int) $data['service_id']]);
        }

        if ($serviceIds->isEmpty() && $capacityWindow?->service_id) {
            $serviceIds = collect([(int) $capacityWindow->service_id]);
        }

        if ($serviceIds->isEmpty()) {
            return collect();
        }

        return Service::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereIn('id', $serviceIds)
            ->get()
            ->sortBy(fn (Service $service) => $serviceIds->search((int) $service->id))
            ->values();
    }

    private function resolvePrimaryService(Collection $services, array $data): Service
    {
        $serviceId = isset($data['service_id'])
            ? (int) $data['service_id']
            : null;

        return $services->firstWhere('id', $serviceId) ?? $services->first();
    }

    private function resolveEndsAt(Carbon $startsAt, Collection $services, array $data): Carbon
    {
        if (! empty($data['ends_at'])) {
            return Carbon::parse($data['ends_at']);
        }

        $durationMinutes = $services->sum(function (Service $service): int {
            return (int) (
                $service->duration_minutes
                ?? $service->duration
                ?? $service->length_minutes
                ?? $service->minutes
                ?? 0
            );
        });

        if ($durationMinutes <= 0) {
            throw ValidationException::withMessages([
                'ends_at' => 'Vybrané služby nemajú nastavené trvanie.',
            ]);
        }

        return $startsAt->copy()->addMinutes($durationMinutes);
    }

    private function syncBookingServices(Booking $booking, Collection $services): void
    {
        $payload = [];

        foreach ($services as $service) {
            $payload[$service->id] = [
                'duration_minutes_snapshot' => (int) ($service->duration_minutes ?? 0),
                'price_snapshot' => $service->self_pay_amount ?? null,
            ];
        }

        $booking->services()->sync($payload);
    }
}