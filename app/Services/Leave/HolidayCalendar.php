<?php

namespace App\Services\Leave;

use App\Models\PublicHoliday;
use Illuminate\Support\Collection;

/**
 * Public holidays applicable to MGE staff (plan 27.2 #3, 13.3).
 *
 * Scope: national holidays, plus state holidays for the state the company
 * operates in (config leave.holiday_state — Penang). Holidays gazetted for other
 * states are recorded but ignored, so the table can hold a full national calendar
 * without affecting deductions here.
 *
 * ⚠️ This calendar is only as complete as what HR has entered. Most Malaysian
 * holidays move each year (lunar and gazetted), so the seeder ships fixed-date
 * ones only and refuses to guess the rest. An absent holiday means a day gets
 * deducted that should not have been — wrong, but visible and complainable.
 * A wrong date would be neither.
 */
class HolidayCalendar
{
    /** @var array<string, string> date (Y-m-d) => holiday name */
    private array $byDate;

    public function __construct(?Collection $holidays = null, ?string $state = null)
    {
        $state ??= config('leave.holiday_state');

        $holidays ??= PublicHoliday::active()
            ->where(function ($q) use ($state) {
                $q->where('scope', 'national')
                    ->orWhere(fn ($q) => $q->where('scope', 'state')->where('state', $state));
            })
            ->get();

        $this->byDate = $holidays
            ->mapWithKeys(fn (PublicHoliday $h) => [$h->date->format('Y-m-d') => $h->name])
            ->all();
    }

    public function isHoliday(\DateTimeInterface $date): bool
    {
        return isset($this->byDate[$date->format('Y-m-d')]);
    }

    public function nameFor(\DateTimeInterface $date): ?string
    {
        return $this->byDate[$date->format('Y-m-d')] ?? null;
    }
}
