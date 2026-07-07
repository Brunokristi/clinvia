<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchDisabledDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DisabledDayService
{
	private const TYPE_HOLIDAY_OPEN = 'holiday_open';

	public function __construct(
		private readonly \App\Modules\Calendar\Services\RecurrenceExpansionService $recurrenceExpansionService,
	) {
	}

	public function isDisabled(Branch $branch, Carbon|string $date): bool
	{
		$normalizedDate = $this->normalizeDate($date);

		if (Schema::hasTable('branch_disabled_days')) {
			$override = BranchDisabledDay::query()
				->where('branch_id', $branch->id)
				->whereDate('date', $normalizedDate)
				->first();

			if ($override) {
				return $override->type !== self::TYPE_HOLIDAY_OPEN;
			}
		}

		return $this->isHolidayDate($normalizedDate);
	}

	public function getDisabledDaysForRange(Branch $branch, Carbon $rangeStart, Carbon $rangeEnd): Collection
	{
		$manualRows = collect();

		if (Schema::hasTable('branch_disabled_days')) {
			$manualRows = BranchDisabledDay::query()
				->where('branch_id', $branch->id)
				->whereBetween('date', [
					$rangeStart->copy()->startOfDay(),
					$rangeEnd->copy()->endOfDay(),
				])
				->orderBy('date')
				->get()
				->toBase();
		}

		$openHolidayOverridesByDate = $manualRows
			->filter(fn (BranchDisabledDay $row) => $row->type === self::TYPE_HOLIDAY_OPEN)
			->keyBy(fn (BranchDisabledDay $row) => $row->date->toDateString());

		$manualClosedByDate = $manualRows
			->filter(fn (BranchDisabledDay $row) => $row->type !== self::TYPE_HOLIDAY_OPEN)
			->map(function (BranchDisabledDay $row): array {
				return [
					'id' => $row->id,
					'date' => $row->date->toDateString(),
					'title' => $row->title,
					'type' => $row->type,
					'reason' => $row->reason,
					'source' => 'manual',
					'is_overridable' => true,
				];
			})
			->keyBy('date');

		$holidayClosuresByDate = $this->getHolidayDaysForRange($rangeStart, $rangeEnd)
			->reject(fn (array $holiday) => $openHolidayOverridesByDate->has($holiday['date']))
			->reject(fn (array $holiday) => $manualClosedByDate->has($holiday['date']))
			->mapWithKeys(function (array $holiday): array {
				$date = $holiday['date'];

				return [
					$date => [
						'id' => 'holiday-'.$date,
						'date' => $date,
						'title' => $holiday['title'],
						'type' => 'holiday',
						'reason' => null,
						'source' => 'holiday',
						'is_overridable' => true,
					],
				];
			});

		return $manualClosedByDate
			->merge($holidayClosuresByDate)
			->sortBy('date')
			->values();
	}

	public function eventCountOnDate(Branch $branch, Carbon|string $date): int
	{
		$normalizedDate = Carbon::parse($this->normalizeDate($date));

		return $this->recurrenceExpansionService->forBranch(
			$branch,
			$normalizedDate->copy()->startOfDay(),
			$normalizedDate->copy()->endOfDay(),
		)->count();
	}

	public function bookingCountOnDate(Branch $branch, Carbon|string $date): int
	{
		return $this->eventCountOnDate($branch, $date);
	}

	private function normalizeDate(Carbon|string $date): string
	{
		return $date instanceof Carbon
			? $date->toDateString()
			: Carbon::parse($date)->toDateString();
	}

	private function isHolidayDate(Carbon|string $date): bool
	{
		$normalizedDate = $this->normalizeDate($date);
		$year = (int) substr($normalizedDate, 0, 4);

		return array_key_exists($normalizedDate, $this->stateHolidayMapForYear($year));
	}

	private function getHolidayDaysForRange(Carbon $rangeStart, Carbon $rangeEnd): Collection
	{
		$startYear = (int) $rangeStart->copy()->startOfDay()->format('Y');
		$endYear = (int) $rangeEnd->copy()->endOfDay()->format('Y');

		$holidays = collect();

		for ($year = $startYear; $year <= $endYear; $year++) {
			foreach ($this->stateHolidayMapForYear($year) as $date => $title) {
				if ($date < $rangeStart->toDateString() || $date > $rangeEnd->toDateString()) {
					continue;
				}

				$holidays->push([
					'date' => $date,
					'title' => $title,
				]);
			}
		}

		return $holidays->sortBy('date')->values();
	}

	private function stateHolidayMapForYear(int $year): array
	{
		$map = collect($this->holidayDefinitionsForYear($year))
			->filter(fn (array $holiday): bool => (bool) ($holiday['is_state_holiday'] ?? false))
			->mapWithKeys(fn (array $holiday): array => [
				$holiday['date'] => $holiday['title'],
			])
			->all();

		ksort($map);

		return $map;
	}

	private function holidayDefinitionsForYear(int $year): array
	{
		$easterSunday = Carbon::createFromTimestamp((int) easter_date($year))->setTimezone(config('app.timezone'));

		return [
			['date' => sprintf('%d-01-01', $year), 'title' => 'Den vzniku Slovenskej republiky', 'is_state_holiday' => true],
			['date' => sprintf('%d-01-06', $year), 'title' => 'Zjavenie Pana', 'is_state_holiday' => false],
			['date' => $easterSunday->copy()->subDays(2)->toDateString(), 'title' => 'Velky piatok', 'is_state_holiday' => false],
			['date' => $easterSunday->copy()->addDay()->toDateString(), 'title' => 'Velkonocny pondelok', 'is_state_holiday' => false],
			['date' => sprintf('%d-05-01', $year), 'title' => 'Sviatok prace', 'is_state_holiday' => false],
			['date' => sprintf('%d-05-08', $year), 'title' => 'Den vitazstva nad fasizmom', 'is_state_holiday' => false],
			['date' => sprintf('%d-07-05', $year), 'title' => 'Sviatok svateho Cyrila a Metoda', 'is_state_holiday' => true],
			['date' => sprintf('%d-08-29', $year), 'title' => 'Vyrocie SNP', 'is_state_holiday' => true],
			['date' => sprintf('%d-09-01', $year), 'title' => 'Den Ustavy Slovenskej republiky', 'is_state_holiday' => true],
			['date' => sprintf('%d-10-28', $year), 'title' => 'Den vzniku samostatneho cesko-slovenskeho statu (nie je dnom pracovneho pokoja)', 'is_state_holiday' => true],
			['date' => sprintf('%d-11-17', $year), 'title' => 'Den boja za slobodu a demokraciu', 'is_state_holiday' => true],
			['date' => sprintf('%d-09-15', $year), 'title' => 'Sedembolestna Panna Maria', 'is_state_holiday' => false],
			['date' => sprintf('%d-11-01', $year), 'title' => 'Sviatok vsetkych svatych', 'is_state_holiday' => false],
			['date' => sprintf('%d-12-24', $year), 'title' => 'Stedry den', 'is_state_holiday' => false],
			['date' => sprintf('%d-12-25', $year), 'title' => 'Prvy sviatok vianocny', 'is_state_holiday' => false],
			['date' => sprintf('%d-12-26', $year), 'title' => 'Druhy sviatok vianocny', 'is_state_holiday' => false],
		];
	}
}
