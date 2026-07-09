<?php

namespace App\Modules\Calendar\Jobs;

use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Models\BookingEventDetail;
use App\Modules\Calendar\Models\GroupEventDetail;
use App\Modules\Calendar\Models\GroupEventParticipant;
use App\Models\Branch;
use App\Models\Service;
use App\Services\EmailNotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        public ?string $recurrenceScope = null,
        public ?string $occurrenceStartsAt = null,
        public array $context = [],
    ) {
    }

    public function handle(EmailNotificationService $emailNotificationService): void
    {
        $event = Event::query()
            ->with(['branch', 'bookingDetail', 'groupDetail', 'participants', 'services'])
            ->find($this->eventId);

        if (! $event) {
            $event = $this->rebuildEventFromSnapshot();

            if (! $event) {
                return;
            }
        }

        try {
            $emailNotificationService->dispatchEventMutation(
                event: $event,
                action: $this->action,
                eventType: $this->eventType,
                scope: $this->recurrenceScope,
                context: array_merge($this->context, [
                    'recipient_emails' => $this->recipientEmails ?? [],
                    'occurrence_starts_at' => $this->occurrenceStartsAt,
                ]),
            );
        } catch (\Throwable $exception) {
            Log::error('Centralized calendar notification failed', [
                'event_id' => $event->id,
                'action' => $this->action,
                'event_type' => $this->eventType,
                'scope' => $this->recurrenceScope,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function rebuildEventFromSnapshot(): ?Event
    {
        $snapshot = $this->context['event_snapshot'] ?? null;

        if (! is_array($snapshot)) {
            return null;
        }

        $event = new Event();
        $event->exists = true;
        $event->id = (int) ($snapshot['id'] ?? $this->eventId);
        $event->branch_id = (int) ($snapshot['branch_id'] ?? 0);
        $event->root_event_id = (int) ($snapshot['root_event_id'] ?? $event->id);
        $event->type = $snapshot['type'] ?? $this->eventType;
        $event->status = $snapshot['status'] ?? 'cancelled';
        $event->starts_at = filled($snapshot['starts_at'] ?? null) ? Carbon::parse((string) $snapshot['starts_at']) : null;
        $event->ends_at = filled($snapshot['ends_at'] ?? null) ? Carbon::parse((string) $snapshot['ends_at']) : null;
        $event->is_recurring = (bool) ($snapshot['is_recurring'] ?? false);
        $event->recurrence_rule = $snapshot['recurrence_rule'] ?? null;
        $event->metadata = $snapshot['metadata'] ?? [];

        $branchSnapshot = $snapshot['branch'] ?? null;
        if (is_array($branchSnapshot)) {
            $branch = new Branch();
            $branch->exists = true;
            $branch->id = (int) ($branchSnapshot['id'] ?? $event->branch_id);
            $branch->name = $branchSnapshot['name'] ?? null;
            $branch->notification_settings = $branchSnapshot['notification_settings'] ?? [];
            $event->setRelation('branch', $branch);
        }

        $services = collect($snapshot['services'] ?? [])->map(function ($serviceSnapshot) {
            $service = new Service();
            $service->exists = true;
            $service->id = (int) ($serviceSnapshot['id'] ?? 0);
            $service->name = $serviceSnapshot['name'] ?? null;

            return $service;
        })->filter(fn ($service) => $service->id > 0)->values();
        $event->setRelation('services', $services);

        $bookingDetailSnapshot = $snapshot['booking_detail'] ?? null;
        if (is_array($bookingDetailSnapshot)) {
            $bookingDetail = new BookingEventDetail();
            $bookingDetail->exists = true;
            $bookingDetail->id = (int) ($bookingDetailSnapshot['id'] ?? 0);
            $bookingDetail->event_id = (int) ($bookingDetailSnapshot['event_id'] ?? $event->id);
            $bookingDetail->patient_id = $bookingDetailSnapshot['patient_id'] ?? null;
            $bookingDetail->patient_name = $bookingDetailSnapshot['patient_name'] ?? null;
            $bookingDetail->patient_email = $bookingDetailSnapshot['patient_email'] ?? null;
            $bookingDetail->patient_phone = $bookingDetailSnapshot['patient_phone'] ?? null;
            $bookingDetail->patient_birth_number = $bookingDetailSnapshot['patient_birth_number'] ?? null;
            $bookingDetail->booking_status = $bookingDetailSnapshot['booking_status'] ?? null;
            $event->setRelation('bookingDetail', $bookingDetail);
        }

        $groupDetailSnapshot = $snapshot['group_detail'] ?? null;
        if (is_array($groupDetailSnapshot)) {
            $groupDetail = new GroupEventDetail();
            $groupDetail->exists = true;
            $groupDetail->id = (int) ($groupDetailSnapshot['id'] ?? 0);
            $groupDetail->event_id = (int) ($groupDetailSnapshot['event_id'] ?? $event->id);
            $groupDetail->service_id = $groupDetailSnapshot['service_id'] ?? null;
            $groupDetail->service_name = $groupDetailSnapshot['service_name'] ?? null;
            $groupDetail->capacity = $groupDetailSnapshot['capacity'] ?? null;
            $groupDetail->reserved_places = $groupDetailSnapshot['reserved_places'] ?? null;
            $groupDetail->group_status = $groupDetailSnapshot['group_status'] ?? null;
            $event->setRelation('groupDetail', $groupDetail);
        }

        $participants = collect($snapshot['participants'] ?? [])->map(function ($participantSnapshot) use ($event) {
            $participant = new GroupEventParticipant();
            $participant->exists = true;
            $participant->id = (int) ($participantSnapshot['id'] ?? 0);
            $participant->event_id = (int) ($participantSnapshot['event_id'] ?? $event->id);
            $participant->patient_id = $participantSnapshot['patient_id'] ?? null;
            $participant->status = $participantSnapshot['status'] ?? null;
            $participant->participant_name = $participantSnapshot['participant_name'] ?? null;
            $participant->participant_email = $participantSnapshot['participant_email'] ?? null;
            $participant->participant_phone = $participantSnapshot['participant_phone'] ?? null;
            $participant->participant_birth_number = $participantSnapshot['participant_birth_number'] ?? null;

            return $participant;
        })->filter(fn ($participant) => $participant->id > 0)->values();
        $event->setRelation('participants', $participants);

        return $event;
    }
}
