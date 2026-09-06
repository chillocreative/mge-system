<?php

namespace Tests\Feature\Leave;

use App\Models\Employee;
use App\Models\LeaveDay;
use App\Models\LeaveEntitlementRule;
use App\Models\LeavePolicySetting;
use App\Models\LeaveQuotaPool;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\WorkPattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase B — verifies the leave policy-engine schema (plan stage P1).
 *
 * These tables are additive: nothing reads them yet, so this test exists to prove
 * the migrations apply cleanly on a fresh database and that the models bind to the
 * right columns and relationships before any engine is built on top of them.
 */
class LeaveSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_policy_engine_tables_exist(): void
    {
        foreach ([
            'work_patterns',
            'public_holidays',
            'leave_policy_settings',
            'leave_entitlement_rules',
            'leave_quota_pools',
            'leave_days',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_existing_tables_were_extended_without_losing_columns(): void
    {
        // The new columns...
        $this->assertTrue(Schema::hasColumns('leave_balances', [
            'carried_forward', 'adjustment_days', 'rule_snapshot', 'calculated_at', 'is_locked',
        ]));
        $this->assertTrue(Schema::hasColumn('leave_types', 'quota_pool_id'));
        $this->assertTrue(Schema::hasColumn('employees', 'work_pattern_id'));

        // ...and, just as importantly, the live ones we must never rename.
        $this->assertTrue(Schema::hasColumns('leave_balances', [
            'entitled_days', 'used_days', 'remaining_days',
        ]));
    }

    public function test_work_pattern_stores_working_days_as_an_array(): void
    {
        $pattern = WorkPattern::create([
            'name' => 'Office 5-day',
            'working_days' => [1, 2, 3, 4, 5],
            'is_default_for' => 'office',
        ]);

        $this->assertSame([1, 2, 3, 4, 5], $pattern->fresh()->working_days);
        $this->assertTrue($pattern->fresh()->is_active);
    }

    public function test_employee_work_pattern_relationship_resolves(): void
    {
        $pattern = WorkPattern::create([
            'name' => 'Site 6-day',
            'working_days' => [1, 2, 3, 4, 5, 6],
            'is_default_for' => 'site',
        ]);

        $employee = Employee::create([
            'employee_no' => 'EMP-SCHEMA-1',
            'first_name' => 'Test',
            'category' => 'site',
            'work_pattern_id' => $pattern->id,
        ]);

        $this->assertTrue($employee->workPattern->is($pattern));
        $this->assertTrue($pattern->employees->contains($employee));
    }

    public function test_quota_pool_groups_leave_types(): void
    {
        $pool = LeaveQuotaPool::create(['name' => 'Medical Leave', 'cap_days' => 60]);

        $mc = LeaveType::create(['name' => 'Sick', 'code' => 'MC-T', 'quota_pool_id' => $pool->id]);
        $hl = LeaveType::create(['name' => 'Hospitalisation', 'code' => 'HL-T', 'quota_pool_id' => $pool->id]);

        $this->assertCount(2, $pool->fresh()->leaveTypes);
        $this->assertTrue($mc->quotaPool->is($pool));
        $this->assertSame('60.00', (string) $hl->quotaPool->cap_days);
    }

    public function test_entitlement_rule_supports_an_open_ended_top_tier(): void
    {
        $type = LeaveType::create(['name' => 'Annual', 'code' => 'AL-T']);

        $top = LeaveEntitlementRule::create([
            'leave_type_id' => $type->id,
            'min_years' => 5,
            'max_years' => null, // no upper bound
            'days' => 16,
            'is_seed_default' => true,
        ]);

        $this->assertNull($top->fresh()->max_years);
        $this->assertTrue($top->fresh()->is_seed_default);
    }

    public function test_policy_settings_support_global_and_per_type_scope(): void
    {
        $type = LeaveType::create(['name' => 'Annual', 'code' => 'AL-T2']);

        LeavePolicySetting::create(['leave_type_id' => null, 'key' => 'leave_year_type', 'value' => 'calendar']);
        LeavePolicySetting::create(['leave_type_id' => $type->id, 'key' => 'carry_forward_enabled', 'value' => '1']);

        $this->assertCount(1, LeavePolicySetting::global()->get());
        $this->assertCount(1, LeavePolicySetting::whereNotNull('leave_type_id')->get());
    }

    public function test_leave_days_record_exclusions_and_split_across_years(): void
    {
        $type = LeaveType::create(['name' => 'Annual', 'code' => 'AL-T3']);
        $employee = Employee::create([
            'employee_no' => 'EMP-SCHEMA-2',
            'first_name' => 'Crossyear',
            'category' => 'office',
        ]);

        $request = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => '2026-12-30',
            'end_date' => '2027-01-02',
            'days_count' => 4,
        ]);

        $rows = [
            ['2026-12-30', 2026, 1.0, true, null],
            ['2026-12-31', 2026, 1.0, true, null],
            ['2027-01-01', 2027, 1.0, false, 'public_holiday'],
            ['2027-01-02', 2027, 0.5, true, null],
        ];

        foreach ($rows as [$date, $year, $fraction, $deducted, $reason]) {
            LeaveDay::create([
                'leave_request_id' => $request->id,
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'date' => $date,
                'year' => $year,
                'fraction' => $fraction,
                'is_deducted' => $deducted,
                'exclusion_reason' => $reason,
            ]);
        }

        // The whole point of leave_days: a cross-year request lands in the right year.
        $this->assertSame(2.0, (float) LeaveDay::deducted()->forYear(2026)->sum('fraction'));
        $this->assertSame(0.5, (float) LeaveDay::deducted()->forYear(2027)->sum('fraction'));

        // The excluded day is retained with its reason, not silently dropped.
        $holiday = LeaveDay::where('is_deducted', false)->sole();
        $this->assertSame('public_holiday', $holiday->exclusion_reason);
    }

    public function test_leave_days_cannot_duplicate_a_date_within_one_request(): void
    {
        $type = LeaveType::create(['name' => 'Annual', 'code' => 'AL-T4']);
        $employee = Employee::create([
            'employee_no' => 'EMP-SCHEMA-3',
            'first_name' => 'Dupe',
            'category' => 'office',
        ]);
        $request = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-02',
            'days_count' => 1,
        ]);

        $attributes = [
            'leave_request_id' => $request->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'date' => '2026-03-02',
            'year' => 2026,
        ];

        LeaveDay::create($attributes);

        $this->expectException(\Illuminate\Database\QueryException::class);
        LeaveDay::create($attributes);
    }

    public function test_public_holidays_can_be_scoped_by_year_and_state(): void
    {
        PublicHoliday::create(['name' => 'New Year', 'date' => '2026-01-01', 'year' => 2026]);
        PublicHoliday::create([
            'name' => 'George Town World Heritage City Day',
            'date' => '2026-07-07',
            'year' => 2026,
            'scope' => 'state',
            'state' => 'Penang',
        ]);
        PublicHoliday::create(['name' => 'New Year', 'date' => '2027-01-01', 'year' => 2027]);

        $this->assertCount(2, PublicHoliday::active()->forYear(2026)->get());
        $this->assertCount(1, PublicHoliday::forYear(2026)->where('scope', 'state')->get());
    }
}
