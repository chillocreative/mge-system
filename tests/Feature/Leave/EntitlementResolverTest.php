<?php

namespace Tests\Feature\Leave;

use App\Models\Employee;
use App\Models\LeaveEntitlementRule;
use App\Models\LeavePolicySetting;
use App\Models\LeaveType;
use App\Services\Leave\EntitlementResolver;
use App\Services\Leave\LeavePolicy;
use Database\Seeders\LeavePolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase D — entitlement resolution (plan 7.2, 7.3).
 *
 * The tier boundary tests matter most. Plan 7.3.8 warns that an inconsistent
 * boundary convention produces employees at exactly 2.00 or 5.00 years' service
 * getting different answers on different screens — a class of bug that is very
 * hard to trace once it reaches HR as a complaint.
 */
class EntitlementResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LeavePolicySeeder::class);
    }

    private function resolver(): EntitlementResolver
    {
        return new EntitlementResolver(new LeavePolicy);
    }

    private function annual(): LeaveType
    {
        return LeaveType::where('code', 'AL')->sole();
    }

    private function employee(?string $hireDate, string $no = 'E1', array $extra = []): Employee
    {
        return Employee::create(array_merge([
            'employee_no' => $no,
            'first_name' => 'Test',
            'category' => 'office',
            'hire_date' => $hireDate,
        ], $extra));
    }

    private function setPolicy(string $key, string $value): void
    {
        LeavePolicySetting::global()->where('key', $key)->update(['value' => $value]);
    }

    // ── Service tiers ──────────────────────────────────────────────────────

    public function test_it_selects_the_tier_matching_length_of_service(): void
    {
        $al = $this->annual();
        $resolver = $this->resolver();
        $employee = $this->employee('2015-01-01');

        // MGE Annual Leave policy: <2y = 14, 2-5y = 16, 5y+ = 18.
        $this->assertSame(14.0, $resolver->resolveTier($employee, $al, 0.0));
        $this->assertSame(14.0, $resolver->resolveTier($employee, $al, 1.99));
        $this->assertSame(16.0, $resolver->resolveTier($employee, $al, 2.0));
        $this->assertSame(16.0, $resolver->resolveTier($employee, $al, 4.99));
        $this->assertSame(18.0, $resolver->resolveTier($employee, $al, 5.0));
        $this->assertSame(18.0, $resolver->resolveTier($employee, $al, 30.0));
    }

    public function test_the_tier_boundary_is_inclusive_below_and_exclusive_above(): void
    {
        $al = $this->annual();
        $resolver = $this->resolver();
        $employee = $this->employee('2015-01-01');

        // ⭐ min <= service < max, applied in exactly one place. An employee at
        // exactly 2.00 years is in the 2-5 tier, not the under-2 tier.
        $this->assertSame(14.0, $resolver->resolveTier($employee, $al, 1.999999));
        $this->assertSame(16.0, $resolver->resolveTier($employee, $al, 2.000000));
    }

    public function test_the_top_tier_is_open_ended(): void
    {
        $employee = $this->employee('1990-01-01');

        $this->assertSame(18.0, $this->resolver()->resolveTier($employee, $this->annual(), 40.0));
    }

    public function test_it_falls_back_to_the_leave_type_default_when_no_tier_matches(): void
    {
        LeaveEntitlementRule::query()->delete();

        $employee = $this->employee('2020-01-01');

        // The seeded default for Annual Leave is 14 days. A system with no tiers
        // configured must still produce a sane number, never zero (plan 7.3.3a).
        $this->assertSame(
            14.0,
            $this->resolver()->resolveTier($employee, $this->annual(), 3.0),
        );
    }

    public function test_a_category_specific_rule_beats_a_general_one(): void
    {
        $al = $this->annual();

        LeaveEntitlementRule::create([
            'leave_type_id' => $al->id,
            'min_years' => 0,
            'max_years' => 2,
            'days' => 10,
            'staff_category' => 'site',
            'is_active' => true,
        ]);

        $office = $this->employee('2025-06-01', 'OFF-1', ['category' => 'office']);
        $site = $this->employee('2025-06-01', 'SITE-1', ['category' => 'site']);

        $resolver = $this->resolver();
        $this->assertSame(14.0, $resolver->resolveTier($office, $al, 1.0));
        $this->assertSame(10.0, $resolver->resolveTier($site, $al, 1.0));
    }

    public function test_an_inactive_rule_is_ignored(): void
    {
        LeaveEntitlementRule::query()->update(['is_active' => false]);

        $employee = $this->employee('2015-01-01');

        // Falls through to the leave type default.
        $this->assertSame(14.0, $this->resolver()->resolveTier($employee, $this->annual(), 10.0));
    }

    // ── Service years ──────────────────────────────────────────────────────

    public function test_service_years_are_measured_from_the_hire_date(): void
    {
        $employee = $this->employee('2020-01-01');

        $this->assertEqualsWithDelta(
            6.0,
            $this->resolver()->serviceYearsAt($employee, Carbon::parse('2026-01-01')),
            0.01,
        );
    }

    public function test_an_employee_with_no_hire_date_gets_zero_service_not_the_top_tier(): void
    {
        $employee = $this->employee(null, 'NOHIRE-1');

        // Guessing here would silently award the most generous tier.
        $this->assertSame(0.0, $this->resolver()->serviceYearsAt($employee, Carbon::parse('2026-01-01')));
        $this->assertSame(14.0, $this->resolver()->entitlementFor($employee, $this->annual(), 2026));
    }

    public function test_service_is_never_negative_before_the_hire_date(): void
    {
        $employee = $this->employee('2026-06-01');

        $this->assertSame(0.0, $this->resolver()->serviceYearsAt($employee, Carbon::parse('2026-01-01')));
    }

    // ── Tier crossing ──────────────────────────────────────────────────────

    public function test_tier_crossing_next_year_holds_the_january_tier_for_the_whole_year(): void
    {
        // Hired 1 Jun 2024, so hits 2 years on 1 Jun 2026 — mid-year.
        $employee = $this->employee('2024-06-01');

        // Default policy is next_year: stays on 14 days for 2026.
        $this->assertSame(14.0, $this->resolver()->entitlementFor($employee, $this->annual(), 2026));

        // And moves to 16 from 2027.
        $this->assertSame(16.0, $this->resolver()->entitlementFor($employee, $this->annual(), 2027));
    }

    public function test_tier_crossing_immediate_grants_the_higher_tier_for_the_whole_year(): void
    {
        $this->setPolicy('tier_crossing', 'immediate');

        $employee = $this->employee('2024-06-01');

        $this->assertSame(16.0, $this->resolver()->entitlementFor($employee, $this->annual(), 2026));
    }

    public function test_tier_crossing_prorate_blends_the_two_tiers_by_month(): void
    {
        $this->setPolicy('tier_crossing', 'prorate');
        $this->setPolicy('proration_rounding', 'none');

        $employee = $this->employee('2024-06-01');

        // 5 months at 14 days + 7 months at 16 days
        //   = (14 * 5/12) + (16 * 7/12) = 5.833 + 9.333 = 15.17
        $this->assertEqualsWithDelta(
            15.17,
            $this->resolver()->entitlementFor($employee, $this->annual(), 2026),
            0.01,
        );
    }

    // ── Proration ──────────────────────────────────────────────────────────

    public function test_a_mid_year_joiner_is_prorated_monthly(): void
    {
        $this->setPolicy('proration_rounding', 'none');

        // Joined 1 Jul 2026 — six months of the year.
        $employee = $this->employee('2026-07-01');

        // 14 days * 6/12 = 7.0
        $this->assertEqualsWithDelta(7.0, $this->resolver()->entitlementFor($employee, $this->annual(), 2026), 0.01);
    }

    public function test_a_full_year_employee_is_not_prorated(): void
    {
        $employee = $this->employee('2015-01-01');

        $this->assertSame(18.0, $this->resolver()->entitlementFor($employee, $this->annual(), 2026));
    }

    public function test_proration_can_be_switched_off(): void
    {
        $this->setPolicy('new_joiner_proration', 'full');

        $employee = $this->employee('2026-07-01');

        $this->assertSame(14.0, $this->resolver()->entitlementFor($employee, $this->annual(), 2026));
    }

    public function test_a_leaver_is_prorated_to_their_resignation_date(): void
    {
        $this->setPolicy('proration_rounding', 'none');

        $employee = $this->employee('2015-01-01', 'LEAVER-1', ['resign_date' => '2026-06-30']);

        // Top tier 18 days, served roughly half the year.
        $entitlement = $this->resolver()->entitlementFor($employee, $this->annual(), 2026);
        $this->assertGreaterThan(8.0, $entitlement);
        $this->assertLessThan(9.5, $entitlement);
    }

    public function test_exit_proration_can_be_switched_off(): void
    {
        $this->setPolicy('exit_proration', '0');

        $employee = $this->employee('2015-01-01', 'LEAVER-2', ['resign_date' => '2026-06-30']);

        $this->assertSame(18.0, $this->resolver()->entitlementFor($employee, $this->annual(), 2026));
    }

    public function test_someone_hired_after_the_year_ends_has_no_entitlement(): void
    {
        $employee = $this->employee('2027-03-01');

        $this->assertSame(0.0, $this->resolver()->entitlementFor($employee, $this->annual(), 2026));
    }

    public function test_someone_who_left_before_the_year_began_has_no_entitlement(): void
    {
        $employee = $this->employee('2015-01-01', 'GONE-1', ['resign_date' => '2025-11-30']);

        $this->assertSame(0.0, $this->resolver()->entitlementFor($employee, $this->annual(), 2026));
    }

    // ── Rounding ───────────────────────────────────────────────────────────

    public function test_rounding_modes(): void
    {
        $r = $this->resolver();

        $this->assertSame(10.33, $r->round(10.333, 'none'));
        $this->assertSame(10.5, $r->round(10.333, 'nearest_half'));
        $this->assertSame(10.5, $r->round(10.1, 'up_half'));
        $this->assertSame(10.0, $r->round(10.4, 'nearest_whole'));
        $this->assertSame(11.0, $r->round(10.1, 'up_whole'));
        $this->assertSame(10.0, $r->round(10.9, 'down'));

        // Unknown mode falls back to nearest_half rather than throwing — an
        // unrecognised setting must not break a balance calculation.
        $this->assertSame(10.5, $r->round(10.4, 'something_unknown'));
    }

    public function test_rounding_is_applied_to_the_final_entitlement(): void
    {
        // Default rounding is nearest_half.
        $employee = $this->employee('2026-08-01'); // 5 months of 14 days = 5.83

        $this->assertSame(6.0, $this->resolver()->entitlementFor($employee, $this->annual(), 2026));
    }

    // ── Policy scoping ─────────────────────────────────────────────────────

    public function test_a_per_leave_type_setting_overrides_the_global_one(): void
    {
        $al = $this->annual();

        LeavePolicySetting::create([
            'leave_type_id' => $al->id,
            'key' => 'tier_crossing',
            'value' => 'immediate',
        ]);

        $employee = $this->employee('2024-06-01');
        $resolver = $this->resolver();

        // Annual Leave uses the override...
        $this->assertSame(16.0, $resolver->entitlementFor($employee, $al, 2026));

        // ...while Sick Leave still follows the global next_year default.
        $mc = LeaveType::where('code', 'MC')->sole();
        $this->assertSame(14.0, $resolver->entitlementFor($employee, $mc, 2026));
    }
}
