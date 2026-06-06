<?php

namespace App\Services;

use App\Models\AppointmentRequest;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchInboxMessage;
use App\Notifications\BranchAdminNotification;
use Illuminate\Support\Facades\Notification;

class BranchInboxMessageService
{
    public function createForContactForm(
        Branch $branch,
        string $senderName,
        ?string $senderEmail,
        ?string $senderPhone,
        ?string $body,
    ): BranchInboxMessage {
        $message = BranchInboxMessage::create([
            'branch_id' => $branch->id,
            'type' => 'contact_form',
            'title' => 'Nový kontaktný formulár',
            'body' => $body,
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'sender_phone' => $senderPhone,
            'read_at' => null,
        ]);

        $this->notifyBranchRecipients(
            branch: $branch,
            settingKey: 'notify_new_contact_form',
            subject: 'Nový kontaktný formulár',
            message: collect([
                'Bol odoslaný nový kontaktný formulár.',
                'Odosielateľ: ' . $senderName,
                $senderEmail ? 'E-mail: ' . $senderEmail : null,
                $senderPhone ? 'Telefón: ' . $senderPhone : null,
                $body ? 'Správa: ' . $body : null,
            ])->filter()->join("\n"),
        );

        return $message;
    }

    public function createForBooking(Booking $booking): BranchInboxMessage
    {
        $booking->loadMissing([
            'branch',
            'service',
            'services',
            'bookingSlot',
        ]);

        $services = $booking->services?->pluck('name')->filter()->join(', ');

        if (! $services && $booking->service) {
            $services = $booking->service->name;
        }

        $startsAt = $booking->bookingSlot?->starts_at
            ? $booking->bookingSlot->starts_at->format('d.m.Y H:i')
            : null;

        $message = BranchInboxMessage::create([
            'branch_id' => $booking->branch_id,
            'booking_id' => $booking->id,
            'type' => 'booking',
            'title' => 'Nová rezervácia',
            'body' => collect([
                $services ? 'Služba: ' . $services : null,
                $startsAt ? 'Termín: ' . $startsAt : null,
                $booking->patient_note ? 'Poznámka: ' . $booking->patient_note : null,
            ])->filter()->join("\n"),
            'sender_name' => $booking->patient_name,
            'sender_email' => $booking->patient_email,
            'sender_phone' => $booking->patient_phone,
            'read_at' => null,
        ]);

        $this->notifyBranchRecipients(
            branch: $booking->branch,
            settingKey: 'notify_new_booking',
            subject: 'Nová rezervácia',
            message: collect([
                'Bola vytvorená nová rezervácia.',
                'Pacient: ' . $booking->patient_name,
                $services ? 'Služba: ' . $services : null,
                $startsAt ? 'Termín: ' . $startsAt : null,
                $booking->patient_email ? 'E-mail: ' . $booking->patient_email : null,
                $booking->patient_phone ? 'Telefón: ' . $booking->patient_phone : null,
            ])->filter()->join("\n"),
        );

        return $message;
    }

    public function createForAppointmentRequest(AppointmentRequest $appointmentRequest): BranchInboxMessage
    {
        $appointmentRequest->loadMissing([
            'branch',
            'services',
        ]);

        $services = $appointmentRequest->services?->pluck('name')->filter()->join(', ');

        $preferredDate = $appointmentRequest->preferred_date
            ? $appointmentRequest->preferred_date->format('d.m.Y')
            : null;

        $preferredPeriod = $this->preferredPeriodLabel($appointmentRequest->preferred_period);

        $message = BranchInboxMessage::create([
            'branch_id' => $appointmentRequest->branch_id,
            'appointment_request_id' => $appointmentRequest->id,
            'type' => 'appointment_request',
            'title' => 'Nová žiadosť o rezerváciu',
            'body' => collect([
                $services ? 'Služby: ' . $services : null,
                $preferredDate ? 'Preferovaný dátum: ' . $preferredDate : null,
                $preferredPeriod ? 'Preferovaný čas: ' . $preferredPeriod : null,
                $appointmentRequest->patient_note ? 'Poznámka: ' . $appointmentRequest->patient_note : null,
            ])->filter()->join("\n"),
            'sender_name' => $appointmentRequest->patient_name,
            'sender_email' => $appointmentRequest->patient_email,
            'sender_phone' => $appointmentRequest->patient_phone,
            'read_at' => null,
        ]);

        $this->notifyBranchRecipients(
            branch: $appointmentRequest->branch,
            settingKey: 'notify_new_appointment_request',
            subject: 'Nová žiadosť o rezerváciu',
            message: collect([
                'Bola odoslaná nová žiadosť o rezerváciu.',
                'Pacient: ' . $appointmentRequest->patient_name,
                $services ? 'Služby: ' . $services : null,
                $preferredDate ? 'Preferovaný dátum: ' . $preferredDate : null,
                $preferredPeriod ? 'Preferovaný čas: ' . $preferredPeriod : null,
                $appointmentRequest->patient_email ? 'E-mail: ' . $appointmentRequest->patient_email : null,
                $appointmentRequest->patient_phone ? 'Telefón: ' . $appointmentRequest->patient_phone : null,
            ])->filter()->join("\n"),
        );

        return $message;
    }

    private function notifyBranchRecipients(
        Branch $branch,
        string $settingKey,
        string $subject,
        string $message,
    ): void {
        $notificationSettings = $this->notificationSettings($branch);

        if (! $notificationSettings['is_enabled']) {
            return;
        }

        if (! ($notificationSettings[$settingKey] ?? false)) {
            return;
        }

        $emails = collect($notificationSettings['notification_emails'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($emails)) {
            return;
        }

        Notification::route('mail', $emails)
            ->notify(new BranchAdminNotification($subject, $message));
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

    private function preferredPeriodLabel(?string $period): ?string
    {
        return match ($period) {
            'morning' => 'Ráno',
            'forenoon' => 'Dopoludnia',
            'afternoon' => 'Popoludní',
            'evening' => 'Večer',
            default => null,
        };
    }
}