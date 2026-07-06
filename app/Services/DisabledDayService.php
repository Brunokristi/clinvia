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

		return array_key_exists($normalizedDate, $this->holidayMapForYear($year));
	}

	private function getHolidayDaysForRange(Carbon $rangeStart, Carbon $rangeEnd): Collection
	{
		$startYear = (int) $rangeStart->copy()->startOfDay()->format('Y');
		$endYear = (int) $rangeEnd->copy()->endOfDay()->format('Y');

		$holidays = collect();

		for ($year = $startYear; $year <= $endYear; $year++) {
			foreach ($this->holidayMapForYear($year) as $date => $title) {
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

	private function holidayMapForYear(int $year): array
	{
		$fixed = [
			sprintf('%d-01-01', $year) => 'Den vzniku Slovenskej republiky',
			sprintf('%d-01-06', $year) => 'Zjavenie Pana',
			sprintf('%d-05-01', $year) => 'Sviatok prace',
			sprintf('%d-05-08', $year) => 'Den vitazstva nad fasizmom',
			sprintf('%d-07-05', $year) => 'Sviatok svateho Cyrila a Metoda',
			sprintf('%d-08-29', $year) => 'Vyrocie SNP',
			sprintf('%d-09-01', $year) => 'Den Ustavy Slovenskej republiky',
			sprintf('%d-09-15', $year) => 'Sedembolestna Panna Maria',
			sprintf('%d-11-01', $year) => 'Sviatok vsetkych svatych',
			sprintf('%d-11-17', $year) => 'Den boja za slobodu a demokraciu',
			sprintf('%d-12-24', $year) => 'Stedry den',
			sprintf('%d-12-25', $year) => 'Prvy sviatok vianocny',
			sprintf('%d-12-26', $year) => 'Druhy sviatok vianocny',
		];

		$easterSunday = Carbon::createFromTimestamp((int) easter_date($year))->setTimezone(config('app.timezone'));

		$fixed[$easterSunday->copy()->subDays(2)->toDateString()] = 'Velky piatok';
		$fixed[$easterSunday->copy()->addDay()->toDateString()] = 'Velkonocny pondelok';

		ksort($fixed);

		return $fixed;
	}
}
