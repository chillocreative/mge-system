<?php

namespace Tests\Feature\Leave;

use App\Models\LeaveEntitlementRule;
use App\Models\LeavePolicySetting;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\User;
use Database\Seeders\LeavePolicySeeder;
use Database\Seeders\PublicHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Phase G — leave policy administration API.
 *
 * The permission tests are not a formality. These endpoints decide how many days
 * every employee is owed; an employee who could edit an entitlement tier could
 * grant themselves leave. Reads are deliberately wider than writes so the apply
 * form can explain a day breakdown without granting write access.
 */
class LeavePolicyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LeavePolicySeeder::class);
        $this->seed(PublicHolidaySeeder::class);

        foreach (['leave.view', 'leave.request', 'leave.manage'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function user(array $permissions = []): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);

        $user->givePermissionTo($permissions);

        return $user;
    }

    /**
     * Every seeded tier is now confirmed MGE policy, so tests about the
     * "unreviewed default" warning create their own unverified row. That keeps
     * them testing the mechanism rather than the seed data.
     */
    private function unverifiedRule(): LeaveEntitlementRule
    {
        return LeaveEntitlementRule::create([
            'leave_type_id' => LeaveType::where('code', 'EL')->sole()->id,
            'min_years' => 0,
            'max_years' => 2,
            'days' => 3,
            'is_seed_default' => true,
            'is_active' => true,
        ]);
    }

    // ── Permission gates ───────────────────────────────────────────────────

    public function test_an_ordinary_employee_cannot_edit_entitlement_tiers(): void
    {
        $employee = $this->user(['leave.view', 'leave.request']);

        // Being able to see the tiers must not imply being able to change them —
        // otherwise anyone could grant themselves more leave.
        $this->actingAs($employee)
            ->postJson('/api/leave-policy/entitlement-rules', [
                'leave_type_id' => 1,
                'min_years' => 0,
                'days' => 99,
            ])
            ->assertForbidden();
    }

    public function test_an_ordinary_employee_cannot_add_a_public_holiday(): void
    {
        $this->actingAs($this->user(['leave.view', 'leave.request']))
            ->postJson('/api/leave-policy/holidays', [
                'name' => 'My Birthday',
                'date' => '2026-06-15',
            ])
            ->assertForbidden();
    }

    public function test_an_ordinary_employee_cannot_read_policy_settings(): void
    {
        $this->actingAs($this->user(['leave.view', 'leave.request']))
            ->getJson('/api/leave-policy/settings')
            ->assertForbidden();
    }

    public function test_an_employee_can_read_the_holiday_calendar(): void
    {
        // Needed so the apply form can explain why a day was not deducted.
        $this->actingAs($this->user(['leave.view', 'leave.request']))
            ->getJson('/api/leave-policy/holidays?year=2026')
            ->assertOk();
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/leave-policy/holidays')->assertUnauthorized();
    }

    // ── Public holidays ────────────────────────────────────────────────────

    public function test_hr_can_add_a_gazetted_holiday(): void
    {
        $this->actingAs($this->user(['leave.manage']))
            ->postJson('/api/leave-policy/holidays', [
                'name' => 'Company Foundation Day',
                'date' => '2026-10-15',
                'scope' => 'national',
            ])
            ->assertCreated();

        $holiday = PublicHoliday::where('name', 'Company Foundation Day')->sole();

        // year is derived, not supplied — it backs the query index.
        $this->assertSame(2026, $holiday->year);
    }

    public function test_adding_a_duplicate_holiday_returns_a_clear_error_not_a_500(): void
    {
        $this->actingAs($this->user(['leave.manage']))
            ->postJson('/api/leave-policy/holidays', ['name' => 'Malaysia Day', 'date' => '2026-09-16'])
            ->assertStatus(422);
    }

    public function test_re_adding_a_deactivated_holiday_reactivates_it(): void
    {
        $h = PublicHoliday::create([
            'name' => 'Some Day', 'date' => '2026-10-20', 'year' => 2026,
            'scope' => 'national', 'is_active' => false,
        ]);

        $this->actingAs($this->user(['leave.manage']))
            ->postJson('/api/leave-policy/holidays', ['name' => 'Some Day', 'date' => '2026-10-20'])
            ->assertOk();

        $this->assertTrue($h->fresh()->is_active);
    }

    public function test_deleting_a_holiday_deactivates_rather_than_destroys_it(): void
    {
        $holiday = PublicHoliday::where('name', 'Labour Day')->sole();

        $this->actingAs($this->user(['leave.manage']))
            ->deleteJson("/api/leave-policy/holidays/{$holiday->id}")
            ->assertOk();

        // Never hard-delete reference data: past leave records still point at it
        // (plan 26.3).
        $this->assertDatabaseHas('public_holidays', ['id' => $holiday->id, 'is_active' => false]);
    }

    public function test_holidays_can_be_filtered_by_year(): void
    {
        PublicHoliday::create(['name' => 'Next Year Thing', 'date' => '2027-04-01', 'year' => 2027]);

        $response = $this->actingAs($this->user(['leave.manage']))
            ->getJson('/api/leave-policy/holidays?year=2027')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    // ── Entitlement tiers ──────────────────────────────────────────────────

    public function test_the_tier_list_flags_unverified_seed_defaults(): void
    {
        // Seeded AL and MC tiers are confirmed policy, so nothing is flagged...
        $clean = $this->actingAs($this->user(['leave.manage']))
            ->getJson('/api/leave-policy/entitlement-rules')->assertOk();
        $this->assertFalse($clean->json('data.has_unverified_defaults'));

        // ...until an unreviewed tier exists. Plan 7.3.10(a): the screen must be
        // able to warn that a number nobody has looked at is in force.
        $this->unverifiedRule();

        $response = $this->actingAs($this->user(['leave.manage']))
            ->getJson('/api/leave-policy/entitlement-rules')->assertOk();

        $this->assertTrue($response->json('data.has_unverified_defaults'));
    }

    public function test_hr_can_confirm_the_tiers_and_clear_the_warning(): void
    {
        $this->unverifiedRule();

        $this->actingAs($this->user(['leave.manage']))
            ->postJson('/api/leave-policy/entitlement-rules/confirm')
            ->assertOk();

        $this->assertSame(0, LeaveEntitlementRule::where('is_seed_default', true)->count());

        $response = $this->actingAs($this->user(['leave.manage']))
            ->getJson('/api/leave-policy/entitlement-rules');

        $this->assertFalse($response->json('data.has_unverified_defaults'));
    }

    public function test_editing_a_tier_marks_it_as_reviewed(): void
    {
        $rule = $this->unverifiedRule();

        $this->actingAs($this->user(['leave.manage']))
            ->putJson("/api/leave-policy/entitlement-rules/{$rule->id}", ['days' => 5])
            ->assertOk();

        $rule->refresh();
        $this->assertSame(5.0, (float) $rule->days);

        // An admin who edited the value has necessarily looked at it.
        $this->assertFalse($rule->is_seed_default);
    }

    public function test_a_tier_with_an_inverted_service_range_is_rejected(): void
    {
        $rule = LeaveEntitlementRule::where('min_years', 2)->first();

        $this->actingAs($this->user(['leave.manage']))
            ->putJson("/api/leave-policy/entitlement-rules/{$rule->id}", ['max_years' => 1])
            ->assertStatus(422);
    }

    public function test_a_partial_tier_update_does_not_trip_the_range_check(): void
    {
        $rule = LeaveEntitlementRule::where('min_years', 2)->first();

        // Sending max_years alone must validate against the stored min_years
        // rather than failing because min_years is absent from the payload.
        $this->actingAs($this->user(['leave.manage']))
            ->putJson("/api/leave-policy/entitlement-rules/{$rule->id}", ['max_years' => 6])
            ->assertOk();
    }

    // ── Work patterns ──────────────────────────────────────────────────────

    public function test_a_work_pattern_rejects_an_invalid_weekday(): void
    {
        $this->actingAs($this->user(['leave.manage']))
            ->postJson('/api/leave-policy/work-patterns', [
                'name' => 'Broken',
                'working_days' => [1, 8], // 8 is not a weekday
            ])
            ->assertStatus(422);
    }

    public function test_hr_can_create_a_work_pattern(): void
    {
        $this->actingAs($this->user(['leave.manage']))
            ->postJson('/api/leave-policy/work-patterns', [
                'name' => 'Rotating crew A',
                'working_days' => [1, 2, 3, 4, 5, 6],
            ])
            ->assertCreated();
    }

    // ── Policy settings ────────────────────────────────────────────────────

    public function test_settings_are_returned_with_the_engine_fallback_defaults(): void
    {
        // Set explicitly: this asserts the flag is reported, not what a
        // developer happens to have in their .env.
        config(['leave.engine_enabled' => false]);

        $response = $this->actingAs($this->user(['leave.manage']))
            ->getJson('/api/leave-policy/settings')
            ->assertOk();

        $this->assertIsArray($response->json('data.settings'));
        $this->assertSame('calendar', $response->json('data.defaults.leave_year_type'));
        $this->assertFalse($response->json('data.engine_enabled'));
    }

    public function test_hr_can_update_a_setting(): void
    {
        $this->actingAs($this->user(['leave.manage']))
            ->putJson('/api/leave-policy/settings', [
                'settings' => [
                    ['key' => 'tier_crossing', 'value' => 'immediate'],
                ],
            ])
            ->assertOk();

        $this->assertSame(
            'immediate',
            LeavePolicySetting::global()->where('key', 'tier_crossing')->sole()->value,
        );
    }

    public function test_updating_a_setting_does_not_create_a_duplicate_row(): void
    {
        $before = LeavePolicySetting::count();

        $this->actingAs($this->user(['leave.manage']))
            ->putJson('/api/leave-policy/settings', [
                'settings' => [['key' => 'tier_crossing', 'value' => 'immediate']],
            ])->assertOk();

        $this->assertSame($before, LeavePolicySetting::count());
    }
}
