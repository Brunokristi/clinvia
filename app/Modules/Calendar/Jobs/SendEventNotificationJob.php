<?php

namespace App\Modules\Calendar\Jobs;

use App\Modules\Calendar\Enums\EventAction;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\BookingRescheduledNotification;
use App\Notifications\BranchAdminNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendEventNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 1800];

    public function __construct(
        public int $eventId,
        public string $action,
        public string $eventType,
        public ?array $recipientEmails = null,
    ) {
    }

    public function handle(): void
    {
        $event = Event::query()
            ->with(['branch', 'bookingDetail', 'groupDetail', 'participants', 'services'])
            ->find($this->eventId);

        if (! $event) {
            return;
        }

        foreach ($this->resolveRecipients($event) as $recipient) {
            $notificationKey = $this->notificationKey($event, $recipient);

            $alreadySent = DB::table('calendar_event_notification_logs')
                ->where('notification_key', $notificationKey)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            DB::table('calendar_event_notification_logs')->insert([
                'notification_key' => $notificationKey,
                'event_id' => $event->id,
                'event_type' => $event->type?->value,
                'action' => $this->action,
                'recipient' => $recipient,
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                $this->send($event, $recipient);

                DB::table('calendar_event_notification_logs')
                    ->where('notification_key', $notificationKey)
                    ->update([
                        'status' => 'sent',
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $exception) {
                DB::table('calendar_event_notification_logs')
                    ->where('notification_key', $notificationKey)
                    ->update([
                        'status' => 'failed',
                        'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                        'updated_at' => now(),
                    ]);

                Log::error('Calendar notification failed', [
                    'event_id' => $event->id,
                    'action' => $this->action,
                    'event_type' => $this->eventType,
                    'recipient' => $recipient,
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }

        foreach ($this->resolveBranchRecipients($event) as $recipient) {
            $notificationKey = $this->notificationKey($event, $recipient . ':branch');

            $alreadySent = DB::table('calendar_event_notification_logs')
                ->where('notification_key', $notificationKey)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            DB::table('calendar_event_notification_logs')->insert([
                'notification_key' => $notificationKey,
                'event_id' => $event->id,
                'event_type' => $event->type?->value,
                'action' => $this->action,
                'recipient' => $recipient,
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                $this->sendBranchNotification($event, $recipient);

                DB::table('calendar_event_notification_logs')
                    ->where('notification_key', $notificationKey)
                    ->update([
                        'status' => 'sent',
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $exception) {
                DB::table('calendar_event_notification_logs')
                    ->where('notification_key', $notificationKey)
                    ->update([
                        'status' => 'failed',
                        'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                        'updated_at' => now(),
                    ]);

                Log::error('Calendar branch notification failed', [
                    'event_id' => $event->id,
                    'action' => $this->action,
                    'event_type' => $this->eventType,
                    'recipient' => $recipient,
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }
    }

    private function send(Event $event, ?string $recipient): void
    {
        if (! $recipient) {
            return;
        }

        if ($event->type === EventType::Booking) {
            $bookingPayload = $this->toLegacyBookingPayload($event);

            if (! $bookingPayload) {
                return;
            }

            if (in_array($this->action, [
                EventAction::EventCreated->value,
                EventAction::EventDuplicated->value,
            ], true)) {
                Notification::route('mail', $recipient)
                    ->notify(new BookingCreatedNotification($bookingPayload));

                return;
            }

            if (in_array($this->action, [
                EventAction::EventCancelled->value,
                EventAction::EventDeleted->value,
                EventAction::EventSeriesDeleted->value,
                EventAction::EventOccurrenceCancelled->value,
                EventAction::EventOccurrenceDeleted->value,
            ], true)) {
                Notification::route('mail', $recipient)
                    ->notify(new BookingCancelledNotification($bookingPayload));

                return;
            }

            if (in_array($this->action, [
                EventAction::EventUpdated->value,
                EventAction::EventRescheduled->value,
                EventAction::EventResized->value,
                EventAction::EventServicesUpdated->value,
                EventAction::EventSeriesUpdated->value,
                EventAction::EventSeriesSplit->value,
                EventAction::EventOccurrenceUpdated->value,
            ], true)) {
                $startsAt = $bookingPayload->starts_at instanceof Carbon
                    ? $bookingPayload->starts_at->copy()
                    : null;

                $endsAt = $bookingPayload->ends_at instanceof Carbon
                    ? $bookingPayload->ends_at->copy()
                    : null;

                Notification::route('mail', $recipient)
                    ->notify(new BookingRescheduledNotification(
                        booking: $bookingPayload,
                        oldStartsAt: $startsAt,
                        oldEndsAt: $endsAt,
                        reason: null,
                    ));
            }

            return;
        }

        if ($event->type === EventType::GroupEvent) {
            $bookingPayload = $this->toLegacyGroupParticipantPayload($event, $recipient);

            if (! $bookingPayload) {
                return;
            }

            if (in_array($this->action, [
                EventAction::EventCreated->value,
                EventAction::EventDuplicated->value,
                EventAction::EventParticipantAdded->value,
            ], true)) {
                Notification::route('mail', $recipient)
                    ->notify(new BookingCreatedNotification($bookingPayload));

                return;
            }

            if (in_array($this->action, [
                EventAction::EventCancelled->value,
                EventAction::EventDeleted->value,
                EventAction::EventSeriesDeleted->value,
                EventAction::EventOccurrenceCancelled->value,
                EventAction::EventOccurrenceDeleted->value,
                EventAction::EventParticipantRemoved->value,
            ], true)) {
                Notification::route('mail', $recipient)
                    ->notify(new BookingCancelledNotification($bookingPayload));

                return;
            }

            if (in_array($this->action, [
                EventAction::EventUpdated->value,
                EventAction::EventRescheduled->value,
                EventAction::EventResized->value,
                EventAction::EventServicesUpdated->value,
                EventAction::EventSeriesUpdated->value,
                EventAction::EventSeriesSplit->value,
                EventAction::EventOccurrenceUpdated->value,
            ], true)) {
                Notification::route('mail', $recipient)
                    ->notify(new BookingRescheduledNotification(
                        booking: $bookingPayload,
                        oldStartsAt: $bookingPayload->starts_at instanceof Carbon ? $bookingPayload->starts_at->copy() : null,
                        oldEndsAt: $bookingPayload->ends_at instanceof Carbon ? $bookingPayload->ends_at->copy() : null,
                        reason: null,
                    ));
            }
        }
    }

    private function resolveRecipients(Event $event): array
    {
        if ($event->type === EventType::Booking) {
            return collect([$event->bookingDetail?->patient_email])
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if ($event->type === EventType::GroupEvent) {
            if (is_array($this->recipientEmails) && $this->recipientEmails !== []) {
                return collect($this->recipientEmails)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }

            return $event->participants
                ->pluck('participant_email')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }

    private function resolveBranchRecipients(Event $event): array
    {
        $settings = array_merge([
            'is_enabled' => false,
            'notification_emails' => [],
            'notify_new_booking' => true,
        ], $event->branch?->notification_settings ?? []);

        if (! ($settings['is_enabled'] ?? false)) {
            return [];
        }

        if (! ($settings['notify_new_booking'] ?? false)) {
            return [];
        }

        return collect($settings['notification_emails'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function sendBranchNotification(Event $event, string $recipient): void
    {
        [$subject, $bodyText] = $this->branchNotificationContent($event);

        if (! $subject || ! $bodyText) {
            return;
        }

        Notification::route('mail', $recipient)
            ->notify(new BranchAdminNotification($subject, $bodyText));
    }

    private function branchNotificationContent(Event $event): array
    {
        $entityLabel = match ($event->type) {
            EventType::Booking => 'rezervácie',
            EventType::GroupEvent => 'skupinového termínu',
            default => 'udalosti',
        };

        $patientLabel = $event->bookingDetail?->patient_name
            ?? $event->participants->pluck('participant_name')->filter()->first()
            ?? 'pacienta';

        return match ($this->action) {
            EventAction::EventCreated->value, EventAction::EventDuplicated->value, EventAction::EventParticipantAdded->value => [
                'Nová zmena v kalendári pobočky',
                "Došlo k vytvoreniu {$entityLabel} pre {$patientLabel}.",
            ],
            EventAction::EventCancelled->value, EventAction::EventDeleted->value, EventAction::EventSeriesDeleted->value, EventAction::EventOccurrenceCancelled->value, EventAction::EventOccurrenceDeleted->value, EventAction::EventParticipantRemoved->value => [
                'Zrušenie udalosti v kalendári pobočky',
                "Došlo k zrušeniu {$entityLabel} pre {$patientLabel}.",
            ],
            EventAction::EventUpdated->value, EventAction::EventRescheduled->value, EventAction::EventResized->value, EventAction::EventServicesUpdated->value, EventAction::EventSeriesUpdated->value, EventAction::EventSeriesSplit->value, EventAction::EventOccurrenceUpdated->value => [
                'Úprava udalosti v kalendári pobočky',
                "Došlo k úprave {$entityLabel} pre {$patientLabel}.",
            ],
            default => [null, null],
        };
    }

    private function notificationKey(Event $event, ?string $recipient): string
    {
        return hash('sha256', implode('|', [
            $event->id,
            $event->updated_at?->format('U') ?? '0',
            $this->action,
            $recipient ?? 'none',
        ]));
    }

    private function toLegacyBookingPayload(Event $event): ?\App\Models\Booking
    {
        if (! $event->bookingDetail || ! $event->starts_at || ! $event->ends_at) {
            return null;
        }

        $legacyBooking = new \App\Models\Booking();

        $legacyBooking->id = $event->id;
        $legacyBooking->branch_id = $event->branch_id;
        $legacyBooking->starts_at = $event->starts_at;
        $legacyBooking->ends_at = $event->ends_at;
        $legacyBooking->patient_name = $event->bookingDetail->patient_name;
        $legacyBooking->patient_email = $event->bookingDetail->patient_email;
        $legacyBooking->patient_phone = $event->bookingDetail->patient_phone;
        $legacyBooking->patient_birth_number = $event->bookingDetail->patient_birth_number;
        $legacyBooking->patient_note = $event->bookingDetail->public_notes;
        $legacyBooking->admin_note = $event->bookingDetail->internal_notes;
        $legacyBooking->status = $event->status;
        $legacyBooking->service_id = $event->services->first()?->id;

        $legacyBooking->setRelation('service', $event->services->first());
        $legacyBooking->setRelation('services', $event->services);

        return $legacyBooking;
    }

    private function toLegacyGroupParticipantPayload(Event $event, string $recipient): ?\App\Models\Booking
    {
        $participant = $event->participants
            ->first(fn ($item) => $item->participant_email === $recipient);

        if (! $participant || ! $event->starts_at || ! $event->ends_at) {
            return null;
        }

        $legacyBooking = new \App\Models\Booking();

        $legacyBooking->id = $event->id;
        $legacyBooking->branch_id = $event->branch_id;
        $legacyBooking->starts_at = $event->starts_at;
        $legacyBooking->ends_at = $event->ends_at;
        $legacyBooking->patient_name = $participant->participant_name;
        $legacyBooking->patient_email = $participant->participant_email;
        $legacyBooking->patient_phone = $participant->participant_phone;
        $legacyBooking->patient_birth_number = null;
        $legacyBooking->patient_note = $participant->notes;
        $legacyBooking->admin_note = $event->groupDetail?->notes;
        $legacyBooking->status = $event->status;
        $legacyBooking->service_id = $event->services->first()?->id;

        $legacyBooking->setRelation('branch', $event->branch);
        $legacyBooking->setRelation('service', $event->services->first());
        $legacyBooking->setRelation('services', $event->services);

        return $legacyBooking;
    }
}
