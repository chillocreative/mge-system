<?php

use App\Models\LeaveEntitlementRule;
use App\Models\LeaveType;
use Illuminate\Database\Migrations\Migration;

/**
 * Applies MGE's confirmed Annual Leave policy, and populates the policy tables.
 *
 * Two things happen here, both necessary:
 *
 * 1. ANNUAL LEAVE BECOMES 14 / 16 / 18 (confirmed by Rahim, 7 Sep 2026).
 *    The tables were seeded with Employment Act minimums (8/12/16) as a
 *    placeholder. MGE is more generous, and 14 also matches the
 *    leave_types.default_days_per_year value the system has used all along —
 *    so the Act figures would have quietly cut junior staff by 6 days.
 *
 *    Only rows still flagged is_seed_default = true are touched. A row someone
 *    has already edited is left exactly as it is: plan 7.3.10(c) is explicit
 *    that silently reverting a tuned entitlement is close to untraceable.
 *
 * 2. NOTHING IS SEEDED HERE.
 *    The policy tables are still empty on production, because deploy.yml runs
 *    `php artisan migrate` and nothing else. That is fixed in deploy.yml by
 *    running the seeders as a deploy step, not from inside a migration:
 *    provisioning reference data is a deployment concern, and seeding from a
 *    migration would also give every test in the suite a pre-populated holiday
 *    calendar and tier set, quietly changing what those tests are measuring.
 *
 *    On production the update below therefore matches nothing (there are no rows
 *    yet) and the deploy seeder creates the tiers with the correct values
 *    directly. Locally, where the Act minimums were already seeded, the update
 *    corrects them. Both paths end up at 14 / 16 / 18.
 *
 * Nothing here changes behaviour while config('leave.engine_enabled') is false,
 * which it is on production.
 */
return new class extends Migration
{
    /** Confirmed MGE Annual Leave tiers, keyed by the tier's lower bound. */
    private const MGE_ANNUAL_TIERS = ['0.00' => 14, '2.00' => 16, '5.00' => 18];

    /** The Employment Act minimums these replaced, for down(). */
    private const ACT_ANNUAL_TIERS = ['0.00' => 8, '2.00' => 12, '5.00' => 16];

    private const MGE_NOTE = 'MGE company policy, confirmed 7 Sep 2026. More generous than the Employment Act minimum (8/12/16).';

    public function up(): void
    {
        $this->retierAnnualLeave(self::MGE_ANNUAL_TIERS, seedDefault: false, note: self::MGE_NOTE);
    }

    public function down(): void
    {
        // Reverses only what up() applied: rows still holding exactly the
        // confirmed MGE values. Anything edited since is left alone, so a
        // rollback cannot silently undo somebody's later correction.
        $this->retierAnnualLeave(
            self::ACT_ANNUAL_TIERS,
            seedDefault: true,
            note: 'Employment Act 1955 minimum.',
            onlyIfDays: self::MGE_ANNUAL_TIERS,
        );
    }

    /**
     * @param  array<string, int>  $tiers  lower bound => days
     * @param  array<string, int>|null  $onlyIfDays  only update when the row still holds this value
     */
    private function retierAnnualLeave(array $tiers, bool $seedDefault, string $note, ?array $onlyIfDays = null): void
    {
        $annual = LeaveType::where('code', 'AL')->first();

        if (! $annual) {
            return;
        }

        foreach ($tiers as $minYears => $days) {
            $query = LeaveEntitlementRule::where('leave_type_id', $annual->id)
                ->where('min_years', $minYears)
                ->whereNull('staff_category')
                ->whereNull('employment_type');

            if ($onlyIfDays !== null) {
                $query->where('days', $onlyIfDays[$minYears]);
            } else {
                // Never overwrite a tier a human has already reviewed.
                $query->where('is_seed_default', true);
            }

            $query->update([
                'days' => $days,
                'is_seed_default' => $seedDefault,
                'notes' => $note,
            ]);
        }
    }
};
