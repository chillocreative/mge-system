<?php

namespace Tests\Feature\Leave;

use App\Models\LeaveEntitlementRule;
use App\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the two data migrations that put MGE's confirmed entitlement policy in
 * place (Rahim, 7 Sep 2026): Annual Leave 14 / 16 / 18, replacing the Employment
 * Act placeholder, and Sick Leave 14 / 18 / 22, whose numbers stay put while its
 * review flag clears.
 *
 * The protective tests are the ones that matter. Plan 7.3.10(c) warns that code
 * which silently reverts an entitlement someone has tuned produces a bug that is
 * almost impossible to trace: the number changes and nothing in the UI records
 * that it happened, or why. Each migration therefore guards on both the review
 * flag and the value, in both directions.
 */
class AnnualLeaveTierMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_07_150001_apply_mge_annual_leave_tiers.php');
    }

    private function annual(): LeaveType
    {
        return LeaveType::where('code', 'AL')->sole();
    }

    private function seedTier(float $min, ?float $max, float $days, bool $seedDefault): LeaveEntitlementRule
    {
        return LeaveEntitlementRule::create([
            'leave_type_id' => $this->annual()->id,
            'min_years' => $min,
            'max_years' => $max,
            'days' => $days,
            'is_seed_default' => $seedDefault,
            'is_active' => true,
        ]);
    }

    public function test_it_upgrades_the_statutory_placeholders_to_mge_policy(): void
    {
        $lower = $this->seedTier(0, 2, 8, seedDefault: true);
        $middle = $this->seedTier(2, 5, 12, seedDefault: true);
        $top = $this->seedTier(5, null, 16, seedDefault: true);

        $this->migration()->up();

        $this->assertSame(14.0, (float) $lower->fresh()->days);
        $this->assertSame(16.0, (float) $middle->fresh()->days);
        $this->assertSame(18.0, (float) $top->fresh()->days);
    }

    public function test_upgraded_tiers_are_marked_as_confirmed_policy(): void
    {
        $rule = $this->seedTier(0, 2, 8, seedDefault: true);

        $this->migration()->up();

        // Confirmed by the business, so the settings screen must stop flagging
        // Annual Leave as an unreviewed statutory default.
        $this->assertFalse($rule->fresh()->is_seed_default);
        $this->assertStringContainsString('MGE company policy', $rule->fresh()->notes);
    }

    public function test_it_never_overwrites_a_tier_someone_has_already_tuned(): void
    {
        // HR has deliberately set the junior tier to 20 days for a special case.
        $tuned = $this->seedTier(0, 2, 20, seedDefault: false);

        $this->migration()->up();

        // Silently resetting this to 14 would be the exact failure plan 7.3.10(c)
        // describes: the number moves and nothing records that it did.
        $this->assertSame(20.0, (float) $tuned->fresh()->days);
        $this->assertFalse($tuned->fresh()->is_seed_default);
    }

    public function test_it_leaves_other_leave_types_alone(): void
    {
        $sick = LeaveType::where('code', 'MC')->sole();
        $rule = LeaveEntitlementRule::create([
            'leave_type_id' => $sick->id,
            'min_years' => 0,
            'max_years' => 2,
            'days' => 14,
            'is_seed_default' => true,
            'is_active' => true,
        ]);

        $this->migration()->up();

        // The Annual Leave migration must not reach into other leave types;
        // Sick Leave is handled by its own migration below.
        $this->assertSame(14.0, (float) $rule->fresh()->days);
        $this->assertTrue($rule->fresh()->is_seed_default);
    }

    public function test_it_does_nothing_when_no_tiers_exist_yet(): void
    {
        // This is production's situation: the tables were created empty, and the
        // deploy seeder is what populates them. The migration must not fail.
        $this->migration()->up();

        $this->assertSame(0, LeaveEntitlementRule::count());
    }

    public function test_rollback_restores_the_statutory_minimums(): void
    {
        $rule = $this->seedTier(0, 2, 8, seedDefault: true);
        $this->migration()->up();
        $this->assertSame(14.0, (float) $rule->fresh()->days);

        $this->migration()->down();

        $this->assertSame(8.0, (float) $rule->fresh()->days);
        $this->assertTrue($rule->fresh()->is_seed_default);
    }

    // ── Sick leave confirmation (2026_09_07_160001) ────────────────────────

    private function sickMigration(): object
    {
        return require database_path('migrations/2026_09_07_160001_confirm_sick_leave_tiers.php');
    }

    private function seedSickTier(float $min, ?float $max, float $days, bool $seedDefault): LeaveEntitlementRule
    {
        return LeaveEntitlementRule::create([
            'leave_type_id' => LeaveType::where('code', 'MC')->sole()->id,
            'min_years' => $min,
            'max_years' => $max,
            'days' => $days,
            'is_seed_default' => $seedDefault,
            'is_active' => true,
        ]);
    }

    public function test_it_confirms_sick_leave_tiers_without_changing_the_numbers(): void
    {
        $lower = $this->seedSickTier(0, 2, 14, seedDefault: true);
        $middle = $this->seedSickTier(2, 5, 18, seedDefault: true);
        $top = $this->seedSickTier(5, null, 22, seedDefault: true);

        $this->sickMigration()->up();

        // The entitlement itself must not move — this is a review-state change.
        $this->assertSame(14.0, (float) $lower->fresh()->days);
        $this->assertSame(18.0, (float) $middle->fresh()->days);
        $this->assertSame(22.0, (float) $top->fresh()->days);

        // What changes is that they are no longer unexamined defaults.
        $this->assertFalse($lower->fresh()->is_seed_default);
        $this->assertFalse($middle->fresh()->is_seed_default);
        $this->assertFalse($top->fresh()->is_seed_default);
    }

    public function test_it_does_not_confirm_a_sick_tier_whose_value_was_changed(): void
    {
        // Someone set the junior MC tier to 16. It is no longer the row we
        // seeded, so its review state is not ours to flip.
        $edited = $this->seedSickTier(0, 2, 16, seedDefault: true);

        $this->sickMigration()->up();

        $this->assertSame(16.0, (float) $edited->fresh()->days);
        $this->assertTrue($edited->fresh()->is_seed_default);
    }

    public function test_confirming_sick_leave_leaves_annual_leave_alone(): void
    {
        $annual = $this->seedTier(0, 2, 8, seedDefault: true);

        $this->sickMigration()->up();

        $this->assertTrue($annual->fresh()->is_seed_default);
        $this->assertSame(8.0, (float) $annual->fresh()->days);
    }

    public function test_sick_leave_confirmation_rolls_back(): void
    {
        $rule = $this->seedSickTier(0, 2, 14, seedDefault: true);

        $this->sickMigration()->up();
        $this->assertFalse($rule->fresh()->is_seed_default);

        $this->sickMigration()->down();
        $this->assertTrue($rule->fresh()->is_seed_default);
        $this->assertSame(14.0, (float) $rule->fresh()->days);
    }

    public function test_rollback_does_not_touch_a_tier_edited_after_the_migration(): void
    {
        $rule = $this->seedTier(0, 2, 8, seedDefault: true);
        $this->migration()->up();

        // Someone corrects it afterwards.
        $rule->update(['days' => 15]);

        $this->migration()->down();

        // down() reverses only what up() applied, so a later correction survives
        // a rollback rather than being quietly discarded.
        $this->assertSame(15.0, (float) $rule->fresh()->days);
    }
}
