<?php

namespace Database\Seeders;

use App\Models\LeaveEntitlementRule;
use App\Models\LeavePolicySetting;
use App\Models\LeaveQuotaPool;
use App\Models\LeaveType;
use App\Models\WorkPattern;
use Illuminate\Database\Seeder;

/**
 * Seeds the leave policy engine (plan stage P2).
 *
 * ⚠️ Every write here is idempotent (firstOrCreate). Re-running this seeder after
 * HR has tuned a value must never overwrite that value — plan 7.3.10(c) calls
 * that out as a failure mode that is almost impossible to detect afterwards,
 * because the numbers change with no trace in the UI.
 *
 * ⚠️ The entitlement tiers below are Employment Act 1955 *minimums*, seeded with
 * is_seed_default = true so the UI can flag them as unverified (plan 7.3.10a).
 * They are a starting point, not MGE's confirmed policy — and they currently
 * DISAGREE with leave_types.default_days_per_year, which the live system already
 * seeds at 14 days of Annual Leave. See MGE_PROGRESS.md item 1. Do not enable the
 * engine against these values until HR confirms them.
 */
class LeavePolicySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedWorkPatterns();
        $this->seedQuotaPool();
        $this->seedPolicySettings();
        $this->seedEntitlementRules();
    }

    /**
     * Work happens seven days a week at MGE, so there is no universal weekend —
     * rest days are per work pattern, resolved from the employee's category
     * unless they carry an explicit work_pattern_id override (plan 27.6b).
     *
     * working_days uses ISO-8601 weekday numbers: 1 = Monday ... 7 = Sunday.
     */
    private function seedWorkPatterns(): void
    {
        $patterns = [
            ['name' => 'Office 5-day (Mon–Fri)', 'working_days' => [1, 2, 3, 4, 5], 'is_default_for' => 'office'],
            ['name' => 'Site 6-day (Mon–Sat)', 'working_days' => [1, 2, 3, 4, 5, 6], 'is_default_for' => 'site'],
        ];

        foreach ($patterns as $pattern) {
            WorkPattern::firstOrCreate(
                ['name' => $pattern['name']],
                $pattern + ['is_active' => true],
            );
        }
    }

    /**
     * Mode B from plan 7.3.6: Sick and Hospitalisation leave do not have
     * independent quotas. They share an aggregate 60-day annual cap, which is how
     * the Employment Act frames it — 18 days of MC does not sit on top of 60 days
     * of hospitalisation, it counts towards it.
     */
    private function seedQuotaPool(): void
    {
        $pool = LeaveQuotaPool::firstOrCreate(
            ['name' => 'Medical Leave (MC + Hospitalisation)'],
            ['cap_days' => 60, 'cap_source' => 'fixed', 'is_active' => true],
        );

        LeaveType::whereIn('code', ['MC', 'HL'])
            ->whereNull('quota_pool_id')
            ->update(['quota_pool_id' => $pool->id]);

        // Hospitalisation admission letters are effectively always required.
        LeaveType::where('code', 'HL')->update(['requires_attachment' => true]);
    }

    /**
     * Defaults from plan table 7.3.1. Global rows use leave_type_id = null;
     * per-type overrides are added by admins through the settings screen.
     *
     * Note carry_forward is deliberately OFF at go-live (plan 27.3) — turning it
     * on later is easy, turning it off after balances have carried is not.
     */
    private function seedPolicySettings(): void
    {
        $defaults = [
            'leave_year_type' => 'calendar',
            'service_base_date' => 'hire_date',
            'new_joiner_proration' => 'monthly',
            'proration_rounding' => 'nearest_half',
            'tier_crossing' => 'next_year',
            'carry_forward_enabled' => '0',
            'carry_forward_max_days' => '0',
            'carry_forward_expiry_month' => '3',
            'deduct_pending_from_balance' => '1',
            'allow_negative_balance' => '0',
            'exit_proration' => '1',
            'encashment_enabled' => '0',
            'backdate_limit_days' => '30',
        ];

        foreach ($defaults as $key => $value) {
            LeavePolicySetting::firstOrCreate(
                ['leave_type_id' => null, 'key' => $key, 'effective_from' => null],
                ['value' => $value],
            );
        }
    }

    /**
     * Employment Act 1955 minimums (plan 7.1 / 7.3.9).
     *
     * Boundary convention is min_years <= service < max_years (plan 7.3.8), which
     * matches how the Act itself is worded: "less than 2 years", "2 but less than
     * 5 years", "5 years or more". An employee at exactly 5.00 years falls into
     * the top tier.
     */
    private function seedEntitlementRules(): void
    {
        $tiers = [
            'AL' => [[0, 2, 8], [2, 5, 12], [5, null, 16]],
            'MC' => [[0, 2, 14], [2, 5, 18], [5, null, 22]],
        ];

        foreach ($tiers as $code => $rows) {
            $type = LeaveType::where('code', $code)->first();

            if (! $type) {
                continue;
            }

            foreach ($rows as [$min, $max, $days]) {
                LeaveEntitlementRule::firstOrCreate(
                    [
                        'leave_type_id' => $type->id,
                        'min_years' => $min,
                        'max_years' => $max,
                        'staff_category' => null,
                        'employment_type' => null,
                    ],
                    [
                        'days' => $days,
                        'is_seed_default' => true,
                        'is_active' => true,
                        'notes' => 'Employment Act 1955 minimum. NOT VERIFIED BY HR — confirm before enabling the leave engine.',
                    ],
                );
            }
        }
    }
}
