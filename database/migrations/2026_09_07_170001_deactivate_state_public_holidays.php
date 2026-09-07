<?php

use App\Models\PublicHoliday;
use Illuminate\Database\Migrations\Migration;

/**
 * Deactivates the state public holiday that the original seeder had added.
 *
 * Scope decision (Rahim, 7 Sep 2026): the leave calendar carries national
 * holidays only. The first PublicHolidaySeeder seeded one Penang state day —
 * George Town World Heritage City Day (2026-07-07) — before that decision, so it
 * is already live and must be switched off to match.
 *
 * Deactivated, not deleted (plan 26.3, "use status, never delete"): the row
 * stays, so any leave already calculated against it is untouched and HR can turn
 * it back on from the Public Holidays screen if they later decide to observe it.
 *
 * Scoped narrowly to scope='state' so it never touches a national holiday, and
 * done once as a migration rather than in the seeder — the seeder runs on every
 * deploy, and a blanket "deactivate state" there would keep overriding any state
 * holiday HR chooses to add through the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        PublicHoliday::where('scope', 'state')
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Reactivate only the specific day this migration is responsible for,
        // so a rollback does not blanket-enable state days added later.
        PublicHoliday::where('scope', 'state')
            ->whereDate('date', '2026-07-07')
            ->where('name', 'George Town World Heritage City Day')
            ->update(['is_active' => true]);
    }
};
