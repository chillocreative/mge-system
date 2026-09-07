<?php

namespace Tests\Feature\Leave;

use App\Models\LeaveEntitlementRule;
use App\Models\LeavePolicySetting;
use App\Models\LeaveQuotaPool;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\WorkPattern;
use Database\Seeders\LeavePolicySeeder;
use Database\Seeders\PublicHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase C — leave policy seed data (plan stage P2).
 *
 * The idempotency tests here are the important ones. Plan 7.3.10(c) warns that a
 * seeder which overwrites HR's tuned values on re-run produces a bug that is
 * almost impossible to trace: entitlements silently revert, and nothing in the
 * UI records that it happened.
 */
class LeaveSeederTest extends TestCase
{
    use RefreshDatabase;

    // NOTE: leave_types are seeded by create_leave_types_table's migration
    // itself (AL, MC, EL, UL, ML, HL), so they already exist after
    // RefreshDatabase. Creating them here again would produce duplicates.

    public function test_it_seeds_work_patterns_for_both_staff_categories(): void
    {
        $this->seed(LeavePolicySeeder::class);

        $office = WorkPattern::where('is_default_for', 'office')->sole();
        $site = WorkPattern::where('is_default_for', 'site')->sole();

        // Office rests Sat + Sun; site rests Sunday only. Work runs seven days a
        // week here, so there is no blanket weekend — see plan 27.6b.
        $this->assertSame([1, 2, 3, 4, 5], $office->working_days);
        $this->assertSame([1, 2, 3, 4, 5, 6], $site->working_days);
    }

    public function test_it_puts_sick_and_hospitalisation_in_one_capped_pool(): void
    {
        $this->seed(LeavePolicySeeder::class);

        $pool = LeaveQuotaPool::sole();
        $this->assertSame('60.00', (string) $pool->cap_days);

        // Mode B: the two types share the cap rather than stacking to 78 days.
        $this->assertEqualsCanonicalizing(
            ['MC', 'HL'],
            $pool->leaveTypes->pluck('code')->all(),
        );

        $this->assertTrue(LeaveType::where('code', 'HL')->sole()->requires_attachment);
    }

    public function test_annual_leave_uses_mge_confirmed_tiers(): void
    {
        $this->seed(LeavePolicySeeder::class);

        $al = LeaveType::where('code', 'AL')->sole();
        $rules = LeaveEntitlementRule::where('leave_type_id', $al->id)
            ->orderBy('min_years')->get();

        $this->assertCount(3, $rules);

        // MGE policy confirmed 7 Sep 2026 — more generous than the Employment
        // Act minimum of 8/12/16, and consistent with the 14 days that
        // leave_types.default_days_per_year has always used.
        $this->assertSame([14.0, 16.0, 18.0], $rules->pluck('days')->map(fn ($d) => (float) $d)->all());

        // The top tier is open-ended: 5 years or more.
        $this->assertNull($rules->last()->max_years);

        // Confirmed policy, so no "unreviewed default" warning for Annual Leave.
        $this->assertTrue($rules->every(fn ($r) => ! $r->is_seed_default));
    }

    public function test_sick_leave_uses_mge_confirmed_tiers(): void
    {
        $this->seed(LeavePolicySeeder::class);

        $mc = LeaveType::where('code', 'MC')->sole();
        $rules = LeaveEntitlementRule::where('leave_type_id', $mc->id)
            ->orderBy('min_years')->get();

        $this->assertSame([14.0, 18.0, 22.0], $rules->pluck('days')->map(fn ($d) => (float) $d)->all());

        // These equal the Employment Act minimum, but they are now a confirmed
        // choice rather than an unexamined fallback — which is what the flag
        // records (plan 7.3.10).
        $this->assertTrue($rules->every(fn ($r) => ! $r->is_seed_default));
    }

    public function test_it_seeds_global_policy_defaults_with_carry_forward_off(): void
    {
        $this->seed(LeavePolicySeeder::class);

        $settings = LeavePolicySetting::global()->pluck('value', 'key');

        $this->assertSame('calendar', $settings['leave_year_type']);
        $this->assertSame('hire_date', $settings['service_base_date']);
        $this->assertSame('monthly', $settings['new_joiner_proration']);
        $this->assertSame('nearest_half', $settings['proration_rounding']);
        $this->assertSame('next_year', $settings['tier_crossing']);

        // Off at go-live by design — easy to switch on later, painful to unwind
        // once balances have already carried.
        $this->assertSame('0', $settings['carry_forward_enabled']);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(LeavePolicySeeder::class);
        $counts = [
            WorkPattern::count(),
            LeaveQuotaPool::count(),
            LeavePolicySetting::count(),
            LeaveEntitlementRule::count(),
        ];

        $this->seed(LeavePolicySeeder::class);

        $this->assertSame($counts, [
            WorkPattern::count(),
            LeaveQuotaPool::count(),
            LeavePolicySetting::count(),
            LeaveEntitlementRule::count(),
        ], 'Re-running the seeder duplicated rows.');
    }

    public function test_reseeding_never_overwrites_a_value_hr_has_tuned(): void
    {
        $this->seed(LeavePolicySeeder::class);

        // HR corrects the statutory minimum to MGE's real policy and confirms it.
        $rule = LeaveEntitlementRule::whereHas('leaveType', fn ($q) => $q->where('code', 'AL'))
            ->where('min_years', 0)->sole();
        $rule->update(['days' => 14, 'is_seed_default' => false]);

        // HR also turns carry forward on.
        LeavePolicySetting::global()->where('key', 'carry_forward_enabled')->update(['value' => '1']);

        $this->seed(LeavePolicySeeder::class);

        $this->assertSame(14.0, (float) $rule->fresh()->days, 'Seeder reverted an HR-tuned entitlement.');
        $this->assertFalse($rule->fresh()->is_seed_default);
        $this->assertSame(
            '1',
            LeavePolicySetting::global()->where('key', 'carry_forward_enabled')->sole()->value,
            'Seeder reverted an HR-tuned policy setting.',
        );
    }

    public function test_it_seeds_only_fixed_date_holidays_and_never_guesses_lunar_ones(): void
    {
        $this->seed(PublicHolidaySeeder::class);

        $names = PublicHoliday::forYear(2026)->pluck('name');

        $this->assertContains('Labour Day', $names);
        $this->assertContains('National Day (Hari Merdeka)', $names);
        $this->assertContains('Malaysia Day', $names);

        // Moving holidays must be entered by HR from the gazette, never guessed —
        // a wrong date silently mis-deducts leave for everyone it spans.
        foreach (['Chinese New Year', 'Hari Raya', 'Deepavali', 'Wesak', 'Thaipusam', 'Awal Muharram'] as $moving) {
            $this->assertFalse(
                $names->contains(fn ($n) => str_contains($n, $moving)),
                "Seeder must not guess the date of a moving holiday: {$moving}",
            );
        }
    }

    public function test_holiday_seeder_is_idempotent(): void
    {
        $this->seed(PublicHolidaySeeder::class);
        $count = PublicHoliday::count();

        $this->seed(PublicHolidaySeeder::class);

        $this->assertSame($count, PublicHoliday::count());
    }
}
