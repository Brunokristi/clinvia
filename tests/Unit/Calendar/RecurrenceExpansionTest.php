<?php

namespace Tests\Unit\Calendar;

use App\Modules\Calendar\Services\RecurrenceExpansionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCalendarFixtures;
use Tests\TestCase;

class RecurrenceExpansionTest extends TestCase
{
    use CreatesCalendarFixtures;
    use RefreshDatabase;

    public function test_weekly_recurring_event_generates_expected_occurrences_in_range(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO', 'WE'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-20']),
        ]);

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-07-06')->startOfDay(),
            Carbon::parse('2026-07-20')->endOfDay(),
        );

        $this->assertSame(
            ['2026-07-06', '2026-07-08', '2026-07-13', '2026-07-15', '2026-07-20'],
            $occurrences->pluck('occurrence_starts_at')->map(fn (Carbon $value) => $value->toDateString())->all(),
        );
        $this->assertTrue($occurrences->every(fn (array $occurrence) => $occurrence['root_event_id'] === $event->id));
    }

    public function test_daily_recurring_event_generates_expected_occurrences(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->dailyRecurrence(2, ['type' => 'on', 'count' => null, 'until' => '2026-07-14']),
        ]);

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-07-06')->startOfDay(),
            Carbon::parse('2026-07-14')->endOfDay(),
        );

        $this->assertSame(
            ['2026-07-06', '2026-07-08', '2026-07-10', '2026-07-12', '2026-07-14'],
            $occurrences->pluck('occurrence_starts_at')->map(fn (Carbon $value) => $value->toDateString())->all(),
        );
    }

    public function test_monthly_recurring_event_generates_expected_occurrences(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-01-15 09:00:00'),
            'ends_at' => Carbon::parse('2026-01-15 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->monthlyRecurrence(1, ['type' => 'on', 'count' => null, 'until' => '2026-04-30']),
        ]);

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-01-01')->startOfDay(),
            Carbon::parse('2026-04-30')->endOfDay(),
        );

        $this->assertSame(
            ['2026-01-15', '2026-02-15', '2026-03-15', '2026-04-15'],
            $occurrences->pluck('occurrence_starts_at')->map(fn (Carbon $value) => $value->toDateString())->all(),
        );
    }

    public function test_monthly_by_weekday_position_generates_expected_occurrences(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-01-12 09:00:00'),
            'ends_at' => Carbon::parse('2026-01-12 09:30:00'),
            'timezone' => 'Europe/Bratislava',
            'is_recurring' => true,
            'recurrence_rule' => [
                'frequency' => 'monthly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'bysetpos' => 2,
                'ends' => ['type' => 'on', 'count' => null, 'until' => '2026-04-30'],
            ],
        ]);

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-01-01')->startOfDay(),
            Carbon::parse('2026-04-30')->endOfDay(),
        );

        $this->assertSame(
            ['2026-01-12', '2026-02-09', '2026-03-09', '2026-04-13'],
            $occurrences->pluck('occurrence_starts_at')->map(fn (Carbon $value) => $value->toDateString())->all(),
        );
    }

    public function test_monthly_day_31_skips_short_months(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-01-31 09:00:00'),
            'ends_at' => Carbon::parse('2026-01-31 09:30:00'),
            'timezone' => 'Europe/Bratislava',
            'is_recurring' => true,
            'recurrence_rule' => $this->monthlyRecurrence(1, ['type' => 'on', 'count' => null, 'until' => '2026-05-31']),
        ]);

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-01-01')->startOfDay(),
            Carbon::parse('2026-05-31')->endOfDay(),
        );

        $this->assertSame(
            ['2026-01-31', '2026-03-31', '2026-05-31'],
            $occurrences->pluck('occurrence_starts_at')->map(fn (Carbon $value) => $value->toDateString())->all(),
        );
    }

    public function test_yearly_recurring_event_generates_expected_occurrences(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'timezone' => 'Europe/Bratislava',
            'is_recurring' => true,
            'recurrence_rule' => [
                'frequency' => 'yearly',
                'interval' => 1,
                'weekdays' => [],
                'ends' => ['type' => 'on', 'count' => null, 'until' => '2028-12-31'],
            ],
        ]);

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-01-01')->startOfDay(),
            Carbon::parse('2028-12-31')->endOfDay(),
        );

        $this->assertSame(
            ['2026-07-06', '2027-07-06', '2028-07-06'],
            $occurrences->pluck('occurrence_starts_at')->map(fn (Carbon $value) => $value->toDateString())->all(),
        );
    }

    public function test_recurrence_expansion_is_limited_to_requested_range(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-01 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-01 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->dailyRecurrence(1, ['type' => 'on', 'count' => null, 'until' => '2026-07-31']),
        ]);

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-07-10')->startOfDay(),
            Carbon::parse('2026-07-12')->endOfDay(),
        );

        $this->assertSame(
            ['2026-07-10', '2026-07-11', '2026-07-12'],
            $occurrences->pluck('occurrence_starts_at')->map(fn (Carbon $value) => $value->toDateString())->all(),
        );
    }

    public function test_until_prevents_occurrences_after_series_end(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-01 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-01 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->dailyRecurrence(1, ['type' => 'on', 'count' => null, 'until' => '2026-07-03']),
        ]);

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-10')->endOfDay(),
        );

        $this->assertSame(['2026-07-01', '2026-07-02', '2026-07-03'], $occurrences->pluck('occurrence_starts_at')->map(fn (Carbon $value) => $value->toDateString())->all());
    }

    public function test_count_generates_the_expected_number_of_occurrences(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-01 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-01 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->dailyRecurrence(1, ['type' => 'after', 'count' => 4, 'until' => null]),
        ]);

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-10')->endOfDay(),
        );

        $this->assertCount(4, $occurrences);
        $this->assertSame(['2026-07-01', '2026-07-02', '2026-07-03', '2026-07-04'], $occurrences->pluck('occurrence_starts_at')->map(fn (Carbon $value) => $value->toDateString())->all());
    }

    public function test_cancelled_override_hides_the_root_occurrence(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-20']),
        ]);

        $this->createRecurringOverride($event, [
            'status' => 'cancelled',
            'recurrence_original_starts_at' => Carbon::parse('2026-07-13 09:00:00'),
            'recurrence_original_ends_at' => Carbon::parse('2026-07-13 09:30:00'),
            'starts_at' => Carbon::parse('2026-07-13 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-13 09:30:00'),
        ]);

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-07-06')->startOfDay(),
            Carbon::parse('2026-07-20')->endOfDay(),
        );

        $this->assertSame(['2026-07-06', '2026-07-20'], $occurrences->pluck('occurrence_starts_at')->map(fn (Carbon $value) => $value->toDateString())->all());
    }

    public function test_moved_override_replaces_the_original_occurrence(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-20']),
        ]);

        $override = $this->createRecurringOverride($event, [
            'recurrence_original_starts_at' => Carbon::parse('2026-07-13 09:00:00'),
            'recurrence_original_ends_at' => Carbon::parse('2026-07-13 09:30:00'),
            'starts_at' => Carbon::parse('2026-07-13 11:00:00'),
            'ends_at' => Carbon::parse('2026-07-13 11:30:00'),
        ]);

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-07-06')->startOfDay(),
            Carbon::parse('2026-07-20')->endOfDay(),
        );

        $movedOccurrence = $occurrences->firstWhere('event_id', $override->id);

        $this->assertNotNull($movedOccurrence);
        $this->assertSame('2026-07-13 11:00:00', $movedOccurrence['occurrence_starts_at']->format('Y-m-d H:i:s'));
        $this->assertSame(3, $occurrences->count());
    }

    public function test_soft_deleted_override_does_not_appear_as_public_output(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createAvailabilityRuleEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 12:00:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-20']),
        ]);

        $override = $this->createRecurringOverride($event, [
            'recurrence_original_starts_at' => Carbon::parse('2026-07-13 09:00:00'),
            'recurrence_original_ends_at' => Carbon::parse('2026-07-13 12:00:00'),
            'starts_at' => Carbon::parse('2026-07-13 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-13 13:00:00'),
        ]);
        $override->delete();

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-07-06')->startOfDay(),
            Carbon::parse('2026-07-20')->endOfDay(),
        );

        $this->assertNull($occurrences->firstWhere('event_id', $override->id));
    }

    public function test_override_has_priority_over_root_occurrence(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-20']),
        ]);

        $override = $this->createRecurringOverride($event, [
            'recurrence_original_starts_at' => Carbon::parse('2026-07-13 09:00:00'),
            'recurrence_original_ends_at' => Carbon::parse('2026-07-13 09:30:00'),
        ]);
        $override->bookingDetail()->update(['patient_name' => 'Override Patient']);

        $occurrences = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-07-06')->startOfDay(),
            Carbon::parse('2026-07-20')->endOfDay(),
        );

        $overrideOccurrence = $occurrences->firstWhere('event_id', $override->id);

        $this->assertNotNull($overrideOccurrence);
        $this->assertSame('Override Patient', $overrideOccurrence['event']->bookingDetail?->patient_name);
        $this->assertNull($occurrences->first(fn (array $occurrence) => $occurrence['event_id'] === $event->id && $occurrence['occurrence_starts_at']->equalTo(Carbon::parse('2026-07-13 09:00:00'))));
    }
}