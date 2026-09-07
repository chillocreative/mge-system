<?php

namespace Database\Seeders;

use App\Models\PublicHoliday;
use Illuminate\Database\Seeder;

/**
 * Seeds NATIONAL Malaysian public holidays for 2026.
 *
 * Scope decision (Rahim, 7 Sep 2026): national holidays only. State holidays
 * (Thaipusam, Nuzul Al-Quran, George Town World Heritage City Day, Penang
 * Governor's Birthday) are deliberately excluded. HR can still add any state day
 * later through the Public Holidays admin screen.
 *
 * Dates cross-checked against two public sources on 7 Sep 2026 and include the
 * gazetted replacement days for holidays that fall on a Sunday. Islamic dates
 * (Aidilfitri, Aidiladha, Awal Muharram, Maulidur Rasul) are set by moon
 * sighting and can shift by a day — HR should confirm against the official
 * federal warta and correct any via the admin screen.
 *
 * Idempotent: safe to re-run on every deploy. HR edits made through the UI are
 * matched by date+name, so a later deploy will not duplicate or revert them.
 */
class PublicHolidaySeeder extends Seeder
{
    public function run(): void
    {
        // Federal national holidays for 2026, including gazetted replacement days.
        // [name, date, scope, state]
        $holidays = [
            ['New Year\'s Day', '2026-01-01', 'national', null],
            ['Chinese New Year', '2026-02-17', 'national', null],
            ['Chinese New Year (Second Day)', '2026-02-18', 'national', null],
            ['Hari Raya Aidilfitri (Additional)', '2026-03-20', 'national', null],
            ['Hari Raya Aidilfitri', '2026-03-21', 'national', null],
            ['Hari Raya Aidilfitri (Second Day)', '2026-03-22', 'national', null],
            ['Hari Raya Aidilfitri (Replacement)', '2026-03-23', 'national', null],
            ['Labour Day', '2026-05-01', 'national', null],
            ['Hari Raya Haji', '2026-05-27', 'national', null],
            ['Wesak Day', '2026-05-31', 'national', null],
            ['Agong\'s Birthday', '2026-06-01', 'national', null],
            ['Wesak Day (Replacement)', '2026-06-02', 'national', null],
            ['Awal Muharram', '2026-06-17', 'national', null],
            ['Maulidur Rasul (Prophet Muhammad\'s Birthday)', '2026-08-25', 'national', null],
            ['National Day (Hari Merdeka)', '2026-08-31', 'national', null],
            ['Malaysia Day', '2026-09-16', 'national', null],
            ['Deepavali', '2026-11-08', 'national', null],
            ['Deepavali (Replacement)', '2026-11-09', 'national', null],
            ['Christmas Day', '2026-12-25', 'national', null],
        ];

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
