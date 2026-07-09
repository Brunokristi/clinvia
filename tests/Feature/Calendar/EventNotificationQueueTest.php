<?php

namespace Tests\Feature\Calendar;

use App\Modules\Calendar\Enums\EventAction;
use App\Modules\Calendar\Jobs\SendEventNotificationJob;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\BranchAdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesCalendarFixtures;
use Tests\TestCase;

class EventNotificationQueueTest extends TestCase
{
    use CreatesCalendarFixtures;
    use RefreshDatabase;

    public function test_booking_created_notification_is_sent_only_once_for_same_job_fingerprint(): void
    {
        Notification::fake();

        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture);

        $job = new SendEventNotificationJob($event->id, EventAction::EventCreated->value, $event->type->value);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);

        Notification::assertSentOnDemandTimes(BookingCreatedNotification::class, 1);
        $this->assertDatabaseCount('email_notifications', 1);
        $this->assertDatabaseHas('email_notifications', [
            'notifiable_type' => \App\Modules\Calendar\Models\Event::class,
            'notifiable_id' => $event->id,
            'notification_type' => 'booking.created',
            'status' => 'sent',
            'recipient_email' => 'fixture.patient@example.com',
        ]);
    }

    public function test_group_event_participant_added_notifies_added_participant(): void
    {
        Notification::fake();

        $fixture = $this->createCalendarFixture();
        $event = $this->createGroupEvent($fixture);
        $this->addGroupParticipant($event, [
            'participant_name' => 'First Participant',
            'participant_email' => 'first.participant@example.com',
        ]);
        $this->addGroupParticipant($event, [
            'participant_name' => 'Second Participant',
            'participant_email' => 'second.participant@example.com',
        ]);
        $event->refresh()->load(['groupDetail', 'participants', 'services']);

        $job = new SendEventNotificationJob($event->id, EventAction::EventParticipantAdded->value, $event->type->value);
        app()->call([$job, 'handle']);

        Notification::assertSentOnDemand(BookingCreatedNotification::class, function (BookingCreatedNotification $notification, array $channels, object $notifiable): bool {
            return ($notifiable->routes['mail'] ?? null) === 'first.participant@example.com';
        });
    }

    public function test_booking_cancellation_is_still_sent_when_event_row_was_deleted_before_job_runs(): void
    {
        Notification::fake();

        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture);

        $context = [
            'old_snapshot' => [
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
            ],
            'event_snapshot' => [
                'id' => $event->id,
                'branch_id' => $event->branch_id,
                'root_event_id' => $event->root_event_id ?? $event->id,
                'type' => $event->type->value,
                'status' => 'cancelled',
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'is_recurring' => (bool) $event->is_recurring,
                'recurrence_rule' => $event->recurrence_rule,
                'metadata' => $event->metadata,
                'branch' => [
                    'id' => $fixture['branch']->id,
                    'name' => $fixture['branch']->name,
                    'notification_settings' => $fixture['branch']->notification_settings,
                ],
                'services' => $event->services->map(fn ($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                ])->values()->all(),
                'booking_detail' => [
                    'id' => $event->bookingDetail->id,
                    'event_id' => $event->id,
                    'patient_id' => $event->bookingDetail->patient_id,
                    'patient_name' => $event->bookingDetail->patient_name,
                    'patient_email' => $event->bookingDetail->patient_email,
                    'patient_phone' => $event->bookingDetail->patient_phone,
                    'patient_birth_number' => $event->bookingDetail->patient_birth_number,
                    'booking_status' => 'cancelled',
                ],
            ],
        ];

        $event->delete();

        $job = new SendEventNotificationJob(
            eventId: $event->id,
            action: EventAction::EventDeleted->value,
            eventType: $event->type->value,
            context: $context,
        );
        app()->call([$job, 'handle']);

        Notification::assertSentOnDemand(BookingCancelledNotification::class, function (BookingCancelledNotification $notification, array $channels, object $notifiable): bool {
            return ($notifiable->routes['mail'] ?? null) === 'fixture.patient@example.com';
        });
    }

    public function test_booking_created_also_notifies_branch_recipients_when_enabled(): void
    {
        Notification::fake();

        $fixture = $this->createCalendarFixture();
        $fixture['branch']->update([
            'notification_settings' => [
                'is_enabled' => true,
                'notify_new_booking' => true,
                'notification_emails' => ['branch@example.com'],
            ],
        ]);

        $event = $this->createBookingEvent($fixture);

        $job = new SendEventNotificationJob($event->id, EventAction::EventCreated->value, $event->type->value);
        app()->call([$job, 'handle']);

        Notification::assertSentOnDemand(BranchAdminNotification::class, function (BranchAdminNotification $notification, array $channels, object $notifiable): bool {
            return ($notifiable->routes['mail'] ?? null) === 'branch@example.com';
        });
    }
}