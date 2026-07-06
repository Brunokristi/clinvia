<?php

namespace Tests\Feature\Calendar;

use App\Modules\Calendar\Services\EventMutationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesCalendarFixtures;
use Tests\TestCase;

class RecurringEventEditTest extends TestCase
{
    use CreatesCalendarFixtures;
    use RefreshDatabase;

    public function test_this_scope_creates_override_and_keeps_series_unchanged(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-27']),
        ]);

        $updated = app(EventMutationService::class)->update($event, [
            'title' => 'Moved occurrence',
            'starts_at' => '2026-07-13 11:00:00',
            'ends_at' => '2026-07-13 11:30:00',
            'occurrence_starts_at' => '2026-07-13 09:00:00',
            'booking_detail' => [
                'patient_name' => 'Override Patient',
            ],
        ], actorId: $fixture['user']->id, scope: 'this');

        $event->refresh();

        $this->assertNotSame($event->id, $updated->id);
        $this->assertSame($event->id, $updated->recurrence_parent_id);
        $this->assertSame('2026-07-13 09:00:00', $updated->recurrence_original_starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-13 11:00:00', $updated->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('Override Patient', $updated->bookingDetail?->patient_name);
        $this->assertTrue($event->is_recurring);
        $this->assertSame('2026-07-06 09:00:00', $event->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame(['frequency' => 'weekly', 'interval' => 1, 'weekdays' => ['MO'], 'ends' => ['type' => 'on', 'count' => null, 'until' => '2026-07-27']], $event->recurrence_rule);
    }

    public function test_series_scope_updates_the_root_event_directly(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createAvailabilityRuleEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 12:00:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'never', 'count' => null, 'until' => null]),
        ]);

        $updated = app(EventMutationService::class)->update($event, [
            'title' => 'Updated rule series',
            'starts_at' => '2026-07-06 10:00:00',
            'ends_at' => '2026-07-06 13:00:00',
            'recurrence_rule' => $this->weeklyRecurrence(['MO', 'WE'], 1, ['type' => 'after', 'count' => 6, 'until' => null]),
            'availability_rule_detail' => [
                'slot_interval_minutes' => 20,
            ],
        ], actorId: $fixture['user']->id, scope: 'series');

        $updated->refresh();

        $this->assertSame($event->id, $updated->id);
        $this->assertSame('Updated rule series', $updated->title);
        $this->assertSame('2026-07-06 10:00:00', $updated->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame(['MO', 'WE'], $updated->recurrence_rule['weekdays']);
        $this->assertSame(6, $updated->recurrence_rule['ends']['count']);
        $this->assertSame(20, $updated->availabilityRuleDetail?->slot_interval_minutes);
    }

    public function test_this_and_following_scope_splits_the_series(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'title' => 'Original series',
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'never', 'count' => null, 'until' => null]),
        ]);

        $newSeries = app(EventMutationService::class)->update($event, [
            'title' => 'Split series',
            'starts_at' => '2026-07-20 10:00:00',
            'ends_at' => '2026-07-20 10:30:00',
            'occurrence_starts_at' => '2026-07-20 09:00:00',
        ], actorId: $fixture['user']->id, scope: 'this_and_following');

        $event->refresh();
        $newSeries->refresh();

        $this->assertNotSame($event->id, $newSeries->id);
        $this->assertSame($event->id, $newSeries->split_from_event_id);
        $this->assertSame('2026-07-13', $event->recurrence_rule['ends']['until']);
        $this->assertSame('2026-07-20 10:00:00', $newSeries->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('Split series', $newSeries->title);
        $this->assertSame('2026-07-20', $newSeries->metadata['series_split_from'] ?? null);
        $this->assertSame(1, $newSeries->recurrence_sequence);
    }

    public function test_cancel_this_marks_only_the_single_occurrence_cancelled(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createGroupEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 14:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 15:00:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-27']),
        ]);

        $cancelled = $this->withRequestBody([
            'occurrence_starts_at' => '2026-07-13 14:00:00',
            'occurrence_date' => '2026-07-13',
        ], fn () => app(EventMutationService::class)->cancel($event, actorId: $fixture['user']->id, scope: 'this'));

        $event->refresh();

        $this->assertNotSame($event->id, $cancelled->id);
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame($event->id, $cancelled->recurrence_parent_id);
        $this->assertSame('confirmed', $event->status);
    }

    public function test_delete_this_creates_a_cancelled_override_instead_of_deleting_root(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-27']),
        ]);

        $this->withRequestBody([], function () use ($event): void {
            request()->merge([
                'occurrence_starts_at' => '2026-07-13 09:00:00',
                'occurrence_date' => '2026-07-13',
            ]);

            app(EventMutationService::class)->delete($event, 'this');
        });

        $event->refresh();
    $override = $event->recurrenceChildren()->first();

        $this->assertNotNull($override);
        $this->assertSame('cancelled', $override->status);
        $this->assertNull($event->deleted_at);
    }

    public function test_cancel_this_and_following_splits_series_and_cancels_new_root(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'never', 'count' => null, 'until' => null]),
        ]);

        $newRoot = app(EventMutationService::class)->update($event, [
            'status' => 'cancelled',
            'occurrence_starts_at' => '2026-07-20 09:00:00',
            'starts_at' => '2026-07-20 09:00:00',
            'ends_at' => '2026-07-20 09:30:00',
        ], actorId: $fixture['user']->id, scope: 'this_and_following');

        $event->refresh();
        $newRoot->refresh();

        $this->assertSame('2026-07-13', $event->recurrence_rule['ends']['until']);
        $this->assertSame('cancelled', $newRoot->status);
    }

    public function test_single_occurrence_update_cannot_change_recurrence_rule(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'never', 'count' => null, 'until' => null]),
        ]);

        $this->expectException(ValidationException::class);

        app(EventMutationService::class)->update($event, [
            'occurrence_starts_at' => '2026-07-13 09:00:00',
            'recurrence_rule' => $this->dailyRecurrence(),
        ], actorId: $fixture['user']->id, scope: 'this');
    }

    private function withRequestBody(array $input, callable $callback)
    {
        request()->replace($input);

        try {
            return $callback();
        } finally {
            request()->replace([]);
        }
    }
}