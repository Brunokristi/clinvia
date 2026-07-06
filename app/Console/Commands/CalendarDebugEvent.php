<?php

namespace App\Console\Commands;

use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventFrontendMapper;
use App\Modules\Calendar\Services\RecurrenceExpansionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalendarDebugEvent extends Command
{
    protected $signature = 'calendar:debug-event
        {eventId : Unified event id}
        {--start= : Range start date (Y-m-d)}
        {--end= : Range end date (Y-m-d)}';

    protected $description = 'Inspect a unified calendar event, its recurrence expansion, and mapper payloads.';

    public function handle(RecurrenceExpansionService $expansionService, EventFrontendMapper $mapper): int
    {
        $event = Event::query()
            ->with(['branch', 'services', 'bookingDetail', 'availabilityRuleDetail', 'groupDetail', 'participants', 'recurrenceParent'])
            ->find($this->argument('eventId'));

        if (! $event) {
            $this->error('Event not found.');

            return self::FAILURE;
        }

        $rootEvent = $event->recurrenceParent ?? $event;
        $rangeStart = $this->resolveRangeStart($rootEvent);
        $rangeEnd = $this->resolveRangeEnd($rootEvent, $rangeStart);

        $occurrences = $expansionService->forBranch(
            $event->branch,
            $rangeStart,
            $rangeEnd,
            [$rootEvent->type->value],
            includeCancelled: true,
        )->filter(fn (array $occurrence) => (int) $occurrence['root_event_id'] === (int) $rootEvent->id)
            ->values();

        $this->info(sprintf('Event %d (%s)', $event->id, $event->type->value));
        $this->table(['Field', 'Value'], [
            ['branch_id', (string) $event->branch_id],
            ['root_event_id', (string) $rootEvent->id],
            ['status', (string) $event->status],
            ['starts_at', (string) optional($event->starts_at)->toIso8601String()],
            ['ends_at', (string) optional($event->ends_at)->toIso8601String()],
            ['is_recurring', $event->is_recurring ? 'yes' : 'no'],
            ['recurrence_parent_id', (string) ($event->recurrence_parent_id ?? '')],
            ['range_start', $rangeStart->toDateString()],
            ['range_end', $rangeEnd->toDateString()],
        ]);

        $this->line('');
        $this->info('Occurrences');
        $this->table(
            ['occurrence_id', 'event_id', 'start', 'end', 'status', 'override', 'cancelled'],
            $occurrences->map(fn (array $occurrence) => [
                $occurrence['occurrence_id'],
                $occurrence['event_id'],
                optional($occurrence['occurrence_starts_at'])->toIso8601String(),
                optional($occurrence['occurrence_ends_at'])->toIso8601String(),
                $occurrence['event']->status,
                $occurrence['is_override'] ? 'yes' : 'no',
                $occurrence['is_cancelled'] ? 'yes' : 'no',
            ])->all(),
        );

        $this->line('');
        $this->info('Calendar payload');
        $this->line(json_encode($mapper->mapForCalendar($event), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

        $this->line('');
        $this->info('Legacy payload');
        $legacyPayload = $occurrences->isNotEmpty()
            ? $mapper->mapExpandedOccurrenceForLegacyPayload($occurrences->first())
            : $mapper->mapForLegacyPayload($event);
        $this->line(json_encode($legacyPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

        return self::SUCCESS;
    }

    private function resolveRangeStart(Event $event): Carbon
    {
        $option = $this->option('start');

        if (filled($option)) {
            return Carbon::parse((string) $option)->startOfDay();
        }

        return ($event->starts_at?->copy() ?? now())->startOfDay();
    }

    private function resolveRangeEnd(Event $event, Carbon $rangeStart): Carbon
    {
        $option = $this->option('end');

        if (filled($option)) {
            return Carbon::parse((string) $option)->endOfDay();
        }

        if ($event->is_recurring) {
            return $rangeStart->copy()->addDays(30)->endOfDay();
        }

        return ($event->ends_at?->copy() ?? $rangeStart->copy())->endOfDay();
    }
}