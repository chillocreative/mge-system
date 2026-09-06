<?php

namespace Database\Seeders;

use App\Models\PublicHoliday;
use Illuminate\Database\Seeder;

/**
 * Seeds FIXED-DATE Malaysian public holidays only (plan stage P2).
 *
 * ⚠️ DELIBERATELY INCOMPLETE — read before adding to this list.
 *
 * Malaysia's calendar is dominated by holidays that move every year because they
 * follow the Islamic (Hijri), Chinese lunisolar, or Hindu calendars, or are fixed
 * by state gazette rather than by rule:
 *
 *   Chinese New Year · Hari Raya Aidilfitri · Hari Raya Aidiladha · Awal Muharram
 *   Maulidur Rasul · Nuzul Al-Quran · Wesak Day · Deepavali · Thaipursam
 *   Agong's Birthday · Penang Governor's Birthday
 *
 * These are NOT seeded, and must not be guessed. A wrong holiday date silently
 * changes how many days are deducted from every employee whose leave spans it —
 * the failure mode plan 27.1 describes, where the number is wrong, nobody
 * notices, and trust in the module is gone by the time it surfaces.
 *
 * HR enters those dates from the official gazette via the Public Holidays admin
 * screen. Until they do, the calculation engine simply has fewer exclusions,
 * which errs towards deducting MORE days — visible and complainable, rather than
 * silently under-deducting.
 *
 * Scope note: this seeds national holidays plus Penang state holidays, per plan
 * 27.3 ("kebangsaan + Pulau Pinang").
 */
class PublicHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            // ── National, fixed date ──
            ['New Year\'s Day', '2026-01-01', 'national', null],
            ['Labour Day', '2026-05-01', 'national', null],
            ['National Day (Hari Merdeka)', '2026-08-31', 'national', null],
            ['Malaysia Day', '2026-09-16', 'national', null],
            ['Christmas Day', '2026-12-25', 'national', null],

            // ── Penang, fixed date ──
            ['George Town World Heritage City Day', '2026-07-07', 'state', 'Penang'],
        ];

        // None of the 2026 dates above fall on a Sunday, so no substitute-day
        // handling is needed this year. That will not hold for every year —
        // check before copying this list forward to 2027.
        foreach ($holidays as [$name, $date, $scope, $state]) {
            // Deliberately not firstOrCreate: the `date` cast means the stored
            // value can carry a zero time component, so an equality lookup on a
            // plain 'Y-m-d' string misses and we insert a duplicate. whereDate
            // compares the date part only, and works on both MySQL and sqlite.
            $exists = PublicHoliday::whereDate('date', $date)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                continue;
            }

            PublicHoliday::create([
                'name' => $name,
                'date' => $date,
                'year' => (int) substr($date, 0, 4),
                'scope' => $scope,
                'state' => $state,
                'is_active' => true,
            ]);
        }
    }
}
