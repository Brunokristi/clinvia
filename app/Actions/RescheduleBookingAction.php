<?php

namespace App\Actions;

use App\Events\BranchCalendarUpdated;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RescheduleBookingAction
{
    public function execute(Branch $branch, Booking $booking, array $data): Booking
    {
        $booking = DB::transaction(function () use ($branch, $booking, $data): Booking {
            $lockedBooking = Booking::query()
                ->where('branch_id', $branch->id)
                ->whereKey($booking->id)
                ->with(['services', 'capacityWindow'])
                ->lockForUpdate()
                ->firstOrFail();

            $services = $this->resolveServices($branch, $lockedBooking, $data);

            if ($services->isEmpty()) {
                throw ValidationException::withMessages([
                    'service_ids' => 'Vyberte aspoň jednu službu.',
                ]);
            }

            $primaryService = $this->resolvePrimaryService($services, $lockedBooking, $data);

            $startsAt = Carbon::parse($data['starts_at']);
            $endsAt = $this->resolveEndsAt($startsAt, $services, $data);
            $recurrence = $data['recurrence'] ?? $lockedBooking->recurrence;

            $supportsRecurrenceColumns = Schema::hasColumn('bookings', 'series_uuid')
                && Schema::hasColumn('bookings', 'recurrence')
                && Schema::hasColumn('bookings', 'recurrence_excluded_dates');

            $shouldResetRecurrenceExcludedDates = (bool) ($data['reset_recurrence_excluded_dates'] ?? false);

            $seriesUuid = $supportsRecurrenceColumns && $recurrence
                ? ($lockedBooking->series_uuid ?: (string) Str::uuid())
                : null;

            $updatePayload = [
                'service_id' => $primaryService->id,
                'capacity_window_id' => null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'patient_name' => $data['patient_name'] ?? $lockedBooking->patient_name,
                'patient_email' => array_key_exists('patient_email', $data)
                    ? $data['patient_email']
                    : $lockedBooking->patient_email,
                'patient_phone' => array_key_exists('patient_phone', $data)
                    ? $data['patient_phone']
                    : $lockedBooking->patient_phone,
                'status' => 'confirmed',
                'admin_note' => $data['admin_note'] ?? $lockedBooking->admin_note,
            ];

            if (Schema::hasColumn('bookings', 'series_uuid')) {
                $updatePayload['series_uuid'] = $seriesUuid;
            }

            if (Schema::hasColumn('bookings', 'recurrence')) {
                $updatePayload['recurrence'] = $supportsRecurrenceColumns ? $recurrence : null;
            }

            if (Schema::hasColumn('bookings', 'recurrence_excluded_dates')) {
                $updatePayload['recurrence_excluded_dates'] = $recurrence
                    ? ($shouldResetRecurrenceExcludedDates ? [] : ($lockedBooking->recurrence_excluded_dates ?? []))
                    : [];
            }

            $lockedBooking->update($updatePayload);

            $this->syncBookingServices($lockedBooking, $services);

            return $lockedBooking;
        });

        BranchCalendarUpdated::dispatch(
            branchId: $booking->branch_id,
            action: 'booking_rescheduled',
            bookingId: $booking->id,
        );

        return $booking->refresh()->load([
            'branch.contacts',
            'service',
            'services',
            'capacityWindow',
        ]);
    }

    private function resolveServices(Branch $branch, Booking $booking, array $data): Collection
    {
        $serviceIds = collect($data['service_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($serviceIds->isEmpty() && ! empty($data['service_id'])) {
            $serviceIds = collect([(int) $data['service_id']]);
        }

        if ($serviceIds->isEmpty()) {
            $serviceIds = $booking->services
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();
        }

        if ($serviceIds->isEmpty() && $booking->service_id) {
            $serviceIds = collect([(int) $booking->service_id]);
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

    private function resolvePrimaryService(Collection $services, Booking $booking, array $data): Service
    {
        $serviceId = isset($data['service_id'])
            ? (int) $data['service_id']
            : (int) $booking->service_id;

        return $services->firstWhere('id', $serviceId) ?? $services->first();
    }

    private function resolveEndsAt(Carbon $startsAt, Collection $services, array $data): Carbon
    {
        if (! empty($data['ends_at'])) {
            return Carbon::parse($data['ends_at']);
        }

        $durationMinutes = $services->sum(fn (Service $service) => (int) ($service->duration_minutes ?? 0));

        if ($durationMinutes <= 0) {
            throw ValidationException::withMessages([
                'service_ids' => 'Vybrané služby nemajú nastavené trvanie.',
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