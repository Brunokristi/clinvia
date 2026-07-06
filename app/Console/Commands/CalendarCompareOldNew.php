<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Services\EventReadAdapterService;
use App\Modules\Calendar\Services\RecurrenceExpansionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CalendarCompareOldNew extends Command
{
    protected $signature = 'calendar:compare-old-new
        {--branch= : Branch id}
        {--start= : Range start date (Y-m-d)}
        {--end= : Range end date (Y-m-d)}';

    protected $description = 'Compare unified event expansion against legacy-shaped adapter payloads.';

    public function handle(RecurrenceExpansionService $expansionService, EventReadAdapterService $readAdapter): int
    {
        $branchId = $this->option('branch');

        if (! filled($branchId)) {
            $this->error('The --branch option is required.');

            return self::FAILURE;
        }

        $branch = Branch::query()->find($branchId);

        if (! $branch) {
            $this->error('Branch not found.');

            return self::FAILURE;
        }

        $rangeStart = filled($this->option('start'))
            ? Carbon::parse((string) $this->option('start'))->startOfDay()
            : now()->startOfDay();
        $rangeEnd = filled($this->option('end'))
            ? Carbon::parse((string) $this->option('end'))->endOfDay()
            : $rangeStart->copy()->addDays(30)->endOfDay();

        $expanded = $expansionService->forBranch($branch, $rangeStart, $rangeEnd, includeCancelled: true);
        $legacy = $readAdapter->getLegacyCalendarPayload($branch, $rangeStart, $rangeEnd);

        $expandedRules = $expanded->pluck('event')
            ->unique('id')
            ->filter(fn ($event) => $event->type === EventType::AvailabilityRule && $event->recurrence_parent_id === null)
            ->count();

        $rows = [
            ['calendarBookings', $expanded->where('event.type', EventType::Booking)->count(), count($legacy['calendarBookings'] ?? [])],
            ['availabilityRules', $expandedRules, count($legacy['availabilityRules'] ?? [])],
            ['calendarCapacityWindows', $expanded->where('event.type', EventType::GroupEvent)->count(), count($legacy['calendarCapacityWindows'] ?? [])],
        ];

        $this->info(sprintf('Comparing branch %d from %s to %s', $branch->id, $rangeStart->toDateString(), $rangeEnd->toDateString()));
        $this->table(['slice', 'expanded', 'legacy_payload'], array_map(fn (array $row) => [
            $row[0],
            (string) $row[1],
            (string) $row[2],
        ], $rows));

        if (! Schema::hasTable('bookings')) {
            $this->warn('Legacy source tables are absent after hard cutover; comparison uses legacy-shaped adapter payloads only.');
        }

        $hasMismatch = collect($rows)->contains(fn (array $row) => $row[1] !== $row[2]);

        if ($hasMismatch) {
            $this->error('Detected payload mismatches between unified expansion and legacy-shaped adapter output.');

            return self::FAILURE;
        }

        $this->info('No count mismatches detected.');

        return self::SUCCESS;
    }
}