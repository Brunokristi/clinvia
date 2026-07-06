<?php

namespace Tests\Feature\Calendar;

use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventReadAdapterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCalendarFixtures;
use Tests\TestCase;

class AvailabilityRuleCountLimitTest extends TestCase
{
    use CreatesCalendarFixtures;
    use RefreshDatabase;

    public function test_rule_bridge_preserves_recurrence_count_limit(): void
    {
        $fixture = $this->createCalendarFixture();

        $response = $this->actingAs($fixture['user'])->put(route('branches.booking.rules.update', $fixture['branch']->id), [
            'rules' => [[
                'date' => '2026-07-06',
                'starts_at' => '09:00',
                'ends_at' => '12:00',
                'service_ids' => [$fixture['service']->id],
                'public_booking_type' => 'immediate_booking',
                'repeats' => true,
                'repeat_every' => 1,
                'repeat_unit' => 'weeks',
                'repeat_weekdays' => ['MO'],
                'repeat_ends_on' => '2026-09-07',
                'recurrence' => [
                    'frequency' => 'weekly',
                    'interval' => 1,
                    'weekdays' => ['MO'],
                    'ends' => [
                        'type' => 'after',
                        'count' => 10,
                        'until' => null,
                    ],
                ],
                'excluded_dates' => [],
                'is_enabled' => true,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $event = Event::query()->where('type', 'availability_rule')->first();

        $this->assertNotNull($event);
        $this->assertSame('after', $event->recurrence_rule['ends']['type'] ?? null);
        $this->assertSame(10, $event->recurrence_rule['ends']['count'] ?? null);

        $payload = app(EventReadAdapterService::class)->getLegacyCalendarPayload(
            $fixture['branch'],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-12-31')->endOfDay(),
        );

        $this->assertCount(1, $payload['availabilityRules'] ?? []);
        $this->assertSame(10, $payload['availabilityRules'][0]['recurrence']['ends']['count'] ?? null);
    }
}