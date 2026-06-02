<?php

namespace App\Actions;

use App\Mail\BookingInformationMail;
use App\Models\Branch;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\BranchInboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CreateBookingAction
{
    public function execute(Branch $branch, BookingSlot $slot, array $data): Booking
    {
        $booking = DB::transaction(function () use ($branch, $slot, $data) {
            $lockedSlot = BookingSlot::query()
                ->whereKey($slot->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $confirmedCount = $lockedSlot->bookings()
                ->where('status', 'confirmed')
                ->count();

            if ($confirmedCount >= $lockedSlot->capacity) {
                throw ValidationException::withMessages([
                    'booking_slot_id' => 'Táto kapacita už nie je dostupná.',
                ]);
            }

            $booking = $lockedSlot->bookings()->create([
                'branch_id' => $branch->id,
                'service_id' => $lockedSlot->service_id,
                'patient_name' => $data['patient_name'],
                'patient_email' => $data['patient_email'] ?? null,
                'patient_phone' => $data['patient_phone'] ?? null,
                'status' => 'confirmed',
                'patient_note' => $data['patient_note'] ?? null,
            ]);

            BranchInboxMessage::create([
                'branch_id' => $branch->id,
                'type' => 'booking_notification',
                'title' => 'Nová rezervácia: ' . $lockedSlot->service->name,
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
            'bookingSlot',
        ]);

        if ($booking->patient_email) {
            Mail::to($booking->patient_email)->send(new BookingInformationMail($booking));
        }

        return $booking;
    }
}