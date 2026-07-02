<?php

namespace Tests\Feature\Calendar;

use App\Actions\CreateBookingAction;
use App\Models\Branch;
use App\Models\BranchDisabledDay;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Services\AdminBookingCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecurringBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_booking_is_expanded_in_calendar_range(): void
    {
        $user = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'global_role' => 'super_admin',
            'is_active' => true,
        ]);

        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-recurring-company',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Recurring Branch',
            'slug' => 'recurring-branch',
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Physio consult',
            'slug' => 'physio-consult',
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        app(CreateBookingAction::class)->execute($branch, [
            'service_id' => $service->id,
            'service_ids' => [$service->id],
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 09:30:00',
            'patient_name' => 'Recurring Patient',
            'patient_email' => 'patient@example.com',
            'patient_phone' => '+421900000001',
            'status' => 'confirmed',
            'notify_patient' => false,
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO', 'WE'],
                'ends' => [
                    'type' => 'after',
                    'count' => 4,
                    'until' => null,
                ],
            ],
        ]);

        $seriesBooking = $branch->bookings()->whereNotNull('series_uuid')->firstOrFail();

        $this->assertNotNull($seriesBooking->series_uuid);
        $this->assertIsArray($seriesBooking->recurrence);

        $calendarBookings = app(AdminBookingCalendarService::class)->getCalendarBookings(
            $branch,
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
        );

        $this->assertCount(4, $calendarBookings);
        $this->assertSame('2026-07-06', $calendarBookings[0]['date']);
        $this->assertSame('2026-07-08', $calendarBookings[1]['date']);
        $this->assertSame('2026-07-13', $calendarBookings[2]['date']);
        $this->assertSame('2026-07-15', $calendarBookings[3]['date']);
    }

    public function test_recurring_booking_occurrence_can_be_deleted_without_removing_series(): void
    {
        Notification::fake();

        $fixture = $this->createRecurringBookingFixture();

        $booking = $fixture['branch']->bookings()->whereNotNull('series_uuid')->firstOrFail();

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.cancel', [
            $fixture['branch']->id,
            $booking->id,
        ]), [
            'delete_scope' => 'occurrence',
            'date' => '2026-07-08',
            'notify_patient' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $booking->refresh();

        $this->assertContains('2026-07-08', $booking->recurrence_excluded_dates);
        $this->assertSame('confirmed', $booking->status);

        $calendarBookings = app(AdminBookingCalendarService::class)->getCalendarBookings(
            $fixture['branch'],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
        );

        $this->assertCount(4, $calendarBookings);
        $this->assertSame(['2026-07-06', '2026-07-13', '2026-07-15', '2026-07-20'], $calendarBookings->pluck('date')->all());

        Notification::assertSentOnDemand(
            BookingCancelledNotification::class,
            function (BookingCancelledNotification $notification, array $channels, object $notifiable): bool {
                return ($notifiable->routes['mail'] ?? null) === 'patient@example.com';
            },
        );
    }

    public function test_recurring_booking_series_can_be_split_from_date_when_rescheduled(): void
    {
        $fixture = $this->createRecurringBookingFixture();

        $booking = $fixture['branch']->bookings()->whereNotNull('series_uuid')->firstOrFail();

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.reschedule', [
            $fixture['branch']->id,
            $booking->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-20 10:00:00',
            'ends_at' => '2026-07-20 10:30:00',
            'reschedule_scope' => 'from_date',
            'date' => '2026-07-13',
            'notify_patient' => false,
        ]);

        $response->assertSessionHasNoErrors();

        $booking->refresh();

        $this->assertSame('2026-07-12', $booking->recurrence['ends']['until']);

        $newSeriesBooking = $fixture['branch']->bookings()
            ->where('id', '!=', $booking->id)
            ->whereNotNull('series_uuid')
            ->firstOrFail();

        $this->assertNotEquals($booking->series_uuid, $newSeriesBooking->series_uuid);
        $this->assertSame('2026-07-20', $newSeriesBooking->starts_at->toDateString());

        $calendarBookings = app(AdminBookingCalendarService::class)->getCalendarBookings(
            $fixture['branch'],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
        );

        $this->assertCount(6, $calendarBookings);
        $this->assertSame(['2026-07-06', '2026-07-08', '2026-07-20', '2026-07-22', '2026-07-27', '2026-07-29'], $calendarBookings->pluck('date')->all());
    }

    public function test_booking_reschedule_to_disabled_day_is_blocked(): void
    {
        $fixture = $this->createRecurringBookingFixture();

        BranchDisabledDay::query()->create([
            'branch_id' => $fixture['branch']->id,
            'created_by' => null,
            'date' => '2026-07-10',
            'title' => 'Closed day',
            'type' => 'holiday',
            'reason' => null,
        ]);

        $booking = $fixture['branch']->bookings()->firstOrFail();

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.reschedule', [
            $fixture['branch']->id,
            $booking->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-10 10:00:00',
            'ends_at' => '2026-07-10 10:30:00',
            'notify_patient' => false,
        ]);

        $response->assertSessionHasErrors('starts_at');

        $booking->refresh();

        $this->assertSame('2026-07-06', $booking->starts_at->toDateString());
    }

    public function test_recurring_booking_can_be_duplicated_with_recurrence(): void
    {
        $fixture = $this->createRecurringBookingFixture();

        $booking = $fixture['branch']->bookings()->whereNotNull('series_uuid')->firstOrFail();

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.duplicate', [
            $fixture['branch']->id,
            $booking->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 09:30:00',
            'patient_name' => 'Recurring Patient',
            'patient_email' => 'patient@example.com',
            'patient_phone' => '+421900000001',
            'patient_note' => 'Follow up',
            'admin_note' => 'Copy',
            'recurrence' => $booking->recurrence,
            'notify_patient' => false,
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame(2, $fixture['branch']->bookings()->whereNotNull('series_uuid')->count());

        $calendarBookings = app(AdminBookingCalendarService::class)->getCalendarBookings(
            $fixture['branch'],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
        );

        $this->assertCount(8, $calendarBookings);
    }

    public function test_recurring_booking_occurrences_on_disabled_days_are_skipped_in_calendar(): void
    {
        $fixture = $this->createRecurringBookingFixture();

        BranchDisabledDay::query()->create([
            'branch_id' => $fixture['branch']->id,
            'created_by' => null,
            'date' => '2026-07-08',
            'title' => 'Closed day',
            'type' => 'holiday',
            'reason' => null,
        ]);

        $calendarBookings = app(AdminBookingCalendarService::class)->getCalendarBookings(
            $fixture['branch'],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
        );

        $this->assertCount(3, $calendarBookings);
        $this->assertSame(['2026-07-06', '2026-07-13', '2026-07-15'], $calendarBookings->pluck('date')->all());
    }

    private function createRecurringBookingFixture(): array
    {
        $user = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'global_role' => 'super_admin',
            'is_active' => true,
        ]);

        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-recurring-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Recurring Branch',
            'slug' => 'recurring-branch-' . Str::random(6),
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Physio consult',
            'slug' => 'physio-consult-' . Str::random(6),
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        app(CreateBookingAction::class)->execute($branch, [
            'service_id' => $service->id,
            'service_ids' => [$service->id],
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 09:30:00',
            'patient_name' => 'Recurring Patient',
            'patient_email' => 'patient@example.com',
            'patient_phone' => '+421900000001',
            'status' => 'confirmed',
            'notify_patient' => false,
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO', 'WE'],
                'ends' => [
                    'type' => 'after',
                    'count' => 4,
                    'until' => null,
                ],
            ],
        ]);

        return compact('user', 'company', 'branch', 'service');
    }
}
