<?php

namespace Tests\Feature\Calendar;

use App\Modules\Calendar\Enums\EventAction;
use App\Modules\Calendar\Jobs\SendEventNotificationJob;
use App\Notifications\BookingChangeSummaryNotification;
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

        Notification::assertSentOnDemand(BookingChangeSummaryNotification::class, function (BookingChangeSummaryNotification $notification, array $channels, object $notifiable): bool {
            return ($notifiable->routes['mail'] ?? null) === 'first.participant@example.com';
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