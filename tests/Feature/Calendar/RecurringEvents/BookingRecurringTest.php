<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;

class BookingRecurringTest extends RecurringEventsTestCase
{
    public function test_booking_recurring_edit_this_creates_exception_and_keeps_patient_data(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'booking_detail' => [
                'patient_name' => 'Recurring Patient',
                'patient_email' => 'recurring.patient@example.com',
            ],
        ]);

        $exception = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 12:00:00',
            'ends_at' => '2026-07-13 13:00:00',
        ], $fixture['user']->id, 'this');

        $this->assertSame('Recurring Patient', $exception->bookingDetail?->patient_name);

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertOccurrenceExists($snapshot, '2026-07-13 14:00');
        $this->assertOccurrenceMissing($snapshot, '2026-07-13 12:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_booking_recurring_delete_this_and_following_keeps_past_occurrences(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->withRequestBody([
            'occurrence_date' => '2026-07-20',
        ], fn () => $this->mutationService()->delete($master, 'this_and_following'));

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertRenderedTimes($snapshot, [
            '2026-07-06 12:00',
            '2026-07-13 12:00',
        ]);
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
