<?php

namespace Tests\Feature\Calendar;

use App\Modules\Calendar\Events\BranchEventUpdated;
use Tests\TestCase;

class BranchEventBroadcastTest extends TestCase
{
    public function test_branch_event_broadcast_uses_expected_channel_name_and_alias(): void
    {
        $broadcast = new BranchEventUpdated(
            branchId: 17,
            eventId: 91,
            rootEventId: 45,
            eventType: 'booking',
            action: 'event.occurrence_updated',
            affectedEventIds: [91, 92],
            recurrenceScope: 'this',
            occurrenceStartsAt: '2026-07-13T09:00:00+00:00',
            version: 2,
        );

        $channel = $broadcast->broadcastOn();
        $payload = $broadcast->broadcastWith();

        $this->assertSame('private-branches.17.events', $channel->name);
        $this->assertSame('event.updated', $broadcast->broadcastAs());
        $this->assertSame(2, $payload['version']);
        $this->assertSame(17, $payload['branch_id']);
        $this->assertSame(91, $payload['event_id']);
        $this->assertSame(45, $payload['root_event_id']);
        $this->assertSame('booking', $payload['event_type']);
        $this->assertSame('event.occurrence_updated', $payload['action']);
        $this->assertSame('this', $payload['scope']);
        $this->assertSame('this', $payload['recurrence_scope']);
        $this->assertSame([91, 92], $payload['affected_event_ids']);
        $this->assertSame('2026-07-13T09:00:00+00:00', $payload['occurrence_starts_at']);
        $this->assertArrayHasKey('occurred_at', $payload);
    }
}