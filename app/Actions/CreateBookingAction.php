<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Branch;
use App\Models\BranchInboxMessage;
use App\Models\Service;
use App\Notifications\BookingCreatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class CreateBookingAction
{
    public function execute(Branch $branch, BookingSlot $slot, array $data): Booking
    {
        $booking = DB::transaction(function () use ($branch, $slot, $data) {
            $lockedSlot = BookingSlot::query()
                ->whereKey($slot->id)
                ->where('branch_id', $branch->id)
                ->with('service')
                ->lockForUpdate()
                ->firstOrFail();

            $confirmedCount = $lockedSlot->bookings()
                ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
                ->count();

            if ($confirmedCount >= $lockedSlot->capacity) {
                throw ValidationException::withMessages([
                    'booking_slot_id' => 'Táto kapacita už nie je dostupná.',
                ]);
            }

            $services = $this->resolveServices($branch, $lockedSlot, $data);
            $mainService = $services->first();

            if (! $mainService) {
                throw ValidationException::withMessages([
                    'service_ids' => 'Vyberte aspoň jednu službu.',
                ]);
            }

            $booking = $lockedSlot->bookings()->create([
                'branch_id' => $branch->id,
                'service_id' => $mainService->id,
                'patient_name' => $data['patient_name'],
                'patient_email' => $data['patient_email'] ?? null,
                'patient_phone' => $data['patient_phone'] ?? null,
                'status' => 'confirmed',
                'patient_note' => $data['patient_note'] ?? null,
                'admin_note' => $data['admin_note'] ?? null,
            ]);

            $this->syncBookingServices($booking, $services);

            BranchInboxMessage::create([
                'branch_id' => $branch->id,
                'type' => 'booking_notification',
                'title' => 'Nová rezervácia: ' . $services->pluck('name')->join(', '),
                'body' => sprintf(
                    '%s rezervoval termín %s – %s.',
                    $booking->patient_name,
                    $lockedSlot->starts_at->format('d.m.Y'),
                    $lockedSlot->starts_at->format('H:i'),
                ),
                'sender_name' => $booking->patient_name,
                'sender_email' => $booking->patient_email,
                'sender_phone' => $booking->patient_phone,
                'booking_id' => $booking->id,
            ]);

            return $booking;
        });

        $booking->load([
            'branch.contacts',
            'service',
            'services',
            'bookingSlot',
        ]);

        if ($booking->patient_email && ($data['notify_patient'] ?? true)) {
            Notification::route('mail', $booking->patient_email)
                ->notify(new BookingCreatedNotification($booking));
        }

        return $booking;
    }

    private function resolveServices(Branch $branch, BookingSlot $slot, array $data)
    {
        $serviceIds = collect($data['service_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($serviceIds->isEmpty() && ! empty($data['service_id'])) {
            $serviceIds = collect([
                (int) $data['service_id'],
            ]);
        }

        if ($serviceIds->isEmpty() && $slot->service_id) {
            $serviceIds = collect([
                (int) $slot->service_id,
            ]);
        }

        if ($serviceIds->isEmpty()) {
            return collect();
        }

        return Service::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->whereIn('id', $serviceIds)
            ->get()
            ->sortBy(function (Service $service) use ($serviceIds) {
                return $serviceIds->search((int) $service->id);
            })
            ->values();
    }

    private function syncBookingServices(Booking $booking, $services): void
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