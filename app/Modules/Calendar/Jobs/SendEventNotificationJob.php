<?php

namespace App\Modules\Calendar\Jobs;

use App\Modules\Calendar\Models\Event;
use App\Services\EmailNotificationService;
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
            return;
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
}
