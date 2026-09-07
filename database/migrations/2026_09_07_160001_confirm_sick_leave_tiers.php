<?php

use App\Models\LeaveEntitlementRule;
use App\Models\LeaveType;
use Illuminate\Database\Migrations\Migration;

/**
 * Marks the Sick Leave tiers as confirmed MGE policy (Rahim, 7 Sep 2026).
 *
 * The numbers do not change: 14 / 18 / 22 stays exactly as seeded. What changes
 * is is_seed_default, from true to false.
 *
 * That distinction is the whole point of the flag. These values equal the
 * Employment Act minimum, so nothing about the entitlement moves — but until now
 * they were an *unexamined* fallback, and the settings screen was right to warn
 * about them. Now they are a decision somebody made. Plan 7.3.10 is about
 * whether a human ever looked at the number, not about what the number is:
 * without this step the system would keep running on a figure nobody had
 * checked, which is precisely the silent-policy failure that section describes.
 *
 * Only rows still flagged as seed defaults are touched, and only where the value
 * is still the seeded one — so a tier someone has since edited is left alone in
 * both directions.
 */
return new class extends Migration
{
    /** The confirmed Sick Leave tiers, keyed by the tier's lower bound. */
    private const SICK_TIERS = ['0.00' => 14, '2.00' => 18, '5.00' => 22];

    private const CONFIRMED_NOTE = 'MGE company policy, confirmed 7 Sep 2026. Matches the Employment Act minimum.';

    private const UNCONFIRMED_NOTE = 'Employment Act 1955 minimum. NOT YET CONFIRMED BY HR — review before relying on it.';

    public function up(): void
    {
        $this->reflagSickLeave(seedDefault: false, note: self::CONFIRMED_NOTE, from: true);
    }

    public function down(): void
    {
        $this->reflagSickLeave(seedDefault: true, note: self::UNCONFIRMED_NOTE, from: false);
    }

    /**
     * @param  bool  $from  only touch rows currently in this is_seed_default state
     */
    private function reflagSickLeave(bool $seedDefault, string $note, bool $from): void
    {
        $sick = LeaveType::where('code', 'MC')->first();

        if (! $sick) {
            return;
        }

        foreach (self::SICK_TIERS as $minYears => $days) {
            LeaveEntitlementRule::where('leave_type_id', $sick->id)
                ->where('min_years', $minYears)
                ->whereNull('staff_category')
                ->whereNull('employment_type')
                // Value guard: if the days have been changed, this is no longer
                // the row we seeded and its review state is not ours to flip.
                ->where('days', $days)
                ->where('is_seed_default', $from)
                ->update([
                    'is_seed_default' => $seedDefault,
                    'notes' => $note,
                ]);
        }
    }
};
