<?php

namespace Tests\Feature\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Database\Seeders\LeavePolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase F — shadow-mode recalculation (plan 27.14 stage P3).
 *
 * The critical property is that shadow mode writes nothing. It is the step that
 * lets the engine be validated against every employee on live data without any
 * of them being affected, so if it silently wrote balances it would defeat its
 * own purpose and change production numbers unannounced.
 */
class RecalculateLeaveBalancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LeavePolicySeeder::class);
        config(['leave.engine_enabled' => true]);
    }

    private function employee(string $no, string $hireDate): Employee
    {
        return Employee::create([
            'employee_no' => $no,
            'first_name' => 'Staff',
            'last_name' => $no,
            'category' => 'office',
            'hire_date' => $hireDate,
        ]);
    }

    public function test_shadow_mode_writes_nothing(): void
    {
        $this->employee('E001', '2015-01-01');

        $this->artisan('leave:recalculate --year=2026')
            ->expectsOutputToContain('SHADOW MODE')
            ->assertSuccessful();

        $this->assertSame(0, LeaveBalance::count(), 'Shadow mode must never write a balance.');
    }

    public function test_it_reports_a_difference_against_a_stored_balance(): void
    {
        $employee = $this->employee('E002', '2015-01-01'); // top tier: 16 days
        $annual = LeaveType::where('code', 'AL')->sole();

        LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annual->id,
            'year' => 2026,
            'entitled_days' => 14, // the old flat default
            'used_days' => 0,
            'remaining_days' => 14,
        ]);

        $this->artisan('leave:recalculate --year=2026 --employee='.$employee->id)
            ->expectsOutputToContain('difference')
            ->assertSuccessful();

        // Still untouched.
        $this->assertSame(14.0, (float) LeaveBalance::sole()->entitled_days);
    }

    public function test_commit_writes_the_balances(): void
    {
        $employee = $this->employee('E003', '2015-01-01');

        $this->artisan('leave:recalculate --year=2026 --commit --employee='.$employee->id)
            ->expectsOutputToContain('Balances written')
            ->assertSuccessful();

        $annual = LeaveType::where('code', 'AL')->sole();
        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $annual->id)
            ->sole();

        $this->assertSame(16.0, (float) $balance->entitled_days);
        $this->assertNotNull($balance->calculated_at);
        $this->assertIsArray($balance->rule_snapshot);
    }

    public function test_a_locked_year_is_never_recalculated(): void
    {
        $employee = $this->employee('E004', '2015-01-01');
        $annual = LeaveType::where('code', 'AL')->sole();

        LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annual->id,
            'year' => 2026,
            'entitled_days' => 99,
            'used_days' => 0,
            'remaining_days' => 99,
            'is_locked' => true,
        ]);

        $this->artisan('leave:recalculate --year=2026 --commit --employee='.$employee->id)
            ->expectsOutputToContain('Locked')
            ->assertSuccessful();

        // A closed year whose balances may already have been paid out must not
        // move (plan 7.3.3c).
        $this->assertSame(99.0, (float) LeaveBalance::where('leave_type_id', $annual->id)->sole()->entitled_days);
    }

    public function test_it_reports_no_differences_when_everything_already_matches(): void
    {
        $employee = $this->employee('E005', '2015-01-01');

        // Write once...
        $this->artisan('leave:recalculate --year=2026 --commit --employee='.$employee->id)->assertSuccessful();

        // ...then a shadow run should find nothing to report.
        $this->artisan('leave:recalculate --year=2026 --employee='.$employee->id)
            ->expectsOutputToContain('No differences')
            ->assertSuccessful();
    }

    public function test_it_fails_cleanly_when_there_are_no_employees(): void
    {
        $this->artisan('leave:recalculate --year=2026')
            ->expectsOutputToContain('No employees found')
            ->assertFailed();
    }
}
