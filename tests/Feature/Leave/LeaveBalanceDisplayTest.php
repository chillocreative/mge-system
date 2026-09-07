<?php

namespace Tests\Feature\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\Leave\LeaveEngine;
use App\Services\LeaveService;
use Database\Seeders\LeavePolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The balance an employee is shown must be the balance the system enforces.
 *
 * Before this, display read the stored leave_balances rows while enforcement
 * computed the figure live, so the two could disagree. On the production
 * snapshot they disagreed for three employees out of four — one was shown 12
 * days while actually being limited to 6. That employee would be refused with no
 * explanation they could act on, and the complaint lands on HR rather than on
 * the system (plan 7.3.7).
 *
 * With the engine on there is now one source of truth.
 */
class LeaveBalanceDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LeavePolicySeeder::class);
    }

    private function service(): LeaveService
    {
        return app(LeaveService::class);
    }

    private function employee(string $hireDate = '2015-01-01'): Employee
    {
        return Employee::create([
            'employee_no' => 'E-'.uniqid(),
            'first_name' => 'Test',
            'category' => 'office',
            'hire_date' => $hireDate,
        ]);
    }

    private function annual(): LeaveType
    {
        return LeaveType::where('code', 'AL')->sole();
    }

    public function test_displayed_balance_matches_what_is_enforced(): void
    {
        config(['leave.engine_enabled' => true]);

        $employee = $this->employee();

        // A stale stored row, of the kind production is full of.
        LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'year' => 2026,
            'entitled_days' => 8,
            'used_days' => 1,
            'remaining_days' => 7,
        ]);

        $displayed = $this->service()->balanceFor($employee->id, 2026)
            ->firstWhere('leave_type_id', $this->annual()->id);

        $enforced = app(LeaveEngine::class)->summary($employee, $this->annual(), 2026);

        // The stale 7 must not be shown when the system would actually allow 18.
        $this->assertSame($enforced['available'], $displayed['remaining_days']);
        $this->assertSame($enforced['entitled'], $displayed['entitled_days']);
        $this->assertSame(18.0, (float) $displayed['entitled_days']);
    }

    public function test_it_keeps_the_field_names_the_ui_reads(): void
    {
        config(['leave.engine_enabled' => true]);

        $row = $this->service()->balanceFor($this->employee()->id, 2026)->first();

        // The React screens read these keys; changing them would blank the page.
        foreach (['leave_type_id', 'entitled_days', 'used_days', 'remaining_days', 'leave_type'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }

        $this->assertSame('AL', $row['leave_type']['code'] ?? $row['leave_type']->code);
    }

    public function test_it_covers_every_active_leave_type_not_just_stored_rows(): void
    {
        config(['leave.engine_enabled' => true]);

        $balances = $this->service()->balanceFor($this->employee()->id, 2026);

        // Nothing is stored yet, but an employee still has an entitlement for
        // every type — showing an empty screen would be wrong.
        $this->assertSame(
            LeaveType::where('is_active', true)->count(),
            $balances->count(),
        );
    }

    public function test_pending_requests_are_reflected_in_the_displayed_balance(): void
    {
        config(['leave.engine_enabled' => true]);

        $employee = $this->employee();

        $this->service()->apply([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
        ], \App\Models\User::create([
            'first_name' => 'A', 'last_name' => 'B',
            'email' => 'a-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'),
        ])->id);

        $row = $this->service()->balanceFor($employee->id, 2026)
            ->firstWhere('leave_type_id', $this->annual()->id);

        // 18 entitled, 5 working days pending -> 13 available, and the employee
        // sees that immediately rather than after approval.
        $this->assertSame(5.0, (float) $row['pending_days']);
        $this->assertSame(13.0, (float) $row['remaining_days']);
    }

    public function test_with_the_engine_off_stored_rows_are_returned_unchanged(): void
    {
        config(['leave.engine_enabled' => false]);

        $employee = $this->employee();

        LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual()->id,
            'year' => 2026,
            'entitled_days' => 8,
            'used_days' => 1,
            'remaining_days' => 7,
        ]);

        $balances = $this->service()->balanceFor($employee->id, 2026);

        // Production behaviour must not shift until the engine is switched on.
        $this->assertCount(1, $balances);
        $this->assertSame(7.0, (float) $balances->first()->remaining_days);
    }
}
