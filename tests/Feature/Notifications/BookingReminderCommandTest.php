<?php

namespace Tests\Feature\Notifications;

use App\Models\Branch;
use App\Models\BranchDisabledDay;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Notifications\BookingReminderNotification;
use App\Support\BookingCalendarInvite;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_reminder_for_one_off_booking_one_day_before(): void
    {
        Notification::fake();

        $fixture = $this->createFixture();

        $event = Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-03 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-03 10:30:00'),
            'timezone' => config('app.timezone', 'Europe/Bratislava'),
            'title' => 'One Off Booking',
            'is_recurring' => false,
        ]);

        $event->bookingDetail()->create([
            'patient_name' => 'One Off Patient',
            'patient_email' => 'oneoff@example.com',
            'patient_phone' => '+421900000555',
            'booking_status' => 'confirmed',
        ]);

        $event->services()->sync([
            $fixture['service']->id => [
                'duration_minutes_snapshot' => 30,
                'price_snapshot' => null,
                'sort_order' => 0,
                'quantity' => 1,
            ],
        ]);

        $this->artisan('bookings:send-reminders', ['--date' => '2026-07-03'])
            ->assertSuccessful();

        Notification::assertSentOnDemand(
            BookingReminderNotification::class,
            function (BookingReminderNotification $notification, array $channels, object $notifiable): bool {
                $mail = $notification->toMail((object) []);
                $ics = (string) ($mail->rawAttachments[0]['data'] ?? '');
                $timezone = BookingCalendarInvite::timezone();

                return $notifiable->routes['mail'] === 'oneoff@example.com'
                    && ! empty($mail->rawAttachments)
                    && ($mail->rawAttachments[0]['name'] ?? null) === 'reservation-reminder.ics'
                    && str_contains($ics, 'DTSTART;TZID=' . $timezone . ':');
            }
        );
    }

    public function test_command_sends_reminder_for_recurring_occurrence_and_skips_disabled_day(): void
    {
        Notification::fake();

        $fixture = $this->createFixture();

        $event = Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-01 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-01 09:30:00'),
            'timezone' => config('app.timezone', 'Europe/Bratislava'),
            'title' => 'Recurring Booking',
            'is_recurring' => true,
            'recurrence_rule' => [
                'frequency' => 'daily',
                'interval' => 1,
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-07-10',
                    'count' => null,
                ],
            ],
            'metadata' => [
                'recurrence_excluded_dates' => [],
            ],
        ]);

        $event->bookingDetail()->create([
            'patient_name' => 'Recurring Patient',
            'patient_email' => 'recurring@example.com',
            'patient_phone' => '+421900000556',
            'booking_status' => 'confirmed',
        ]);

        $event->services()->sync([
            $fixture['service']->id => [
                'duration_minutes_snapshot' => 30,
                'price_snapshot' => null,
                'sort_order' => 0,
                'quantity' => 1,
            ],
        ]);

        BranchDisabledDay::query()->create([
            'branch_id' => $fixture['branch']->id,
            'created_by' => null,
            'date' => '2026-07-04',
            'title' => 'Closed day',
            'type' => 'holiday',
            'reason' => null,
        ]);

        $this->artisan('bookings:send-reminders', ['--date' => '2026-07-04'])
            ->assertSuccessful();

        Notification::assertNothingSent();

        $this->artisan('bookings:send-reminders', ['--date' => '2026-07-05'])
            ->assertSuccessful();

        Notification::assertSentOnDemand(
            BookingReminderNotification::class,
            function (BookingReminderNotification $notification, array $channels, object $notifiable): bool {
                $mail = $notification->toMail((object) []);

                return $notifiable->routes['mail'] === 'recurring@example.com'
                    && ! empty($mail->rawAttachments)
                    && ($mail->rawAttachments[0]['name'] ?? null) === 'reservation-reminder.ics';
            }
        );
    }

    private function createFixture(): array
    {
        $user = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => 'password',
            'global_role' => 'super_admin',
            'is_active' => true,
        ]);

        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-reminder-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Reminder Branch',
            'slug' => 'reminder-branch-' . Str::random(6),
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Consultation',
            'slug' => 'consultation-' . Str::random(8),
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        return compact('user', 'company', 'branch', 'service');
    }
}
