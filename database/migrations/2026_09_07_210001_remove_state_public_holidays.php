<?php

use App\Models\PublicHoliday;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes state public holidays from the calendar entirely (Rahim, 7 Sep 2026).
 *
 * The previous migration deactivated the seeded George Town World Heritage City
 * Day so it would stop affecting leave; this removes the now-inactive state
 * row from the list altogether, since MGE's calendar is national-only and a
 * greyed permanent row is just clutter.
 *
 * This is reference data mis-seeded before the national-only decision, not a
 * business record — so removal is the "genuine data-entry cleanup" case, not the
 * "never hard-delete" one (plan 26.3). down() re-creates it (deactivated) so the
 * migration is still reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        PublicHoliday::where('scope', 'state')->where('is_active', false)->delete();
    }

    public function down(): void
    {
        PublicHoliday::firstOrCreate(
            ['date' => '2026-07-07', 'name' => 'George Town World Heritage City Day'],
            ['year' => 2026, 'scope' => 'state', 'state' => 'Penang', 'is_active' => false],
        );
    }
};
