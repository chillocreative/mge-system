<?php

namespace Tests\Feature\Employee;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ciri 9 — when an employee stops being active, their login stops too.
 *
 * AuthService already blocks any user whose status is not 'active', so the piece
 * that was missing is propagation: marking an employee resigned/inactive must
 * flip their linked user account to non-active, or a former employee keeps their
 * login. Reactivating restores it.
 */
class EmploymentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function service(): EmployeeService
    {
        return app(EmployeeService::class);
    }

    private function employeeWithLogin(string $status = 'active'): array
    {
        $user = User::create([
            'first_name' => 'Log', 'last_name' => 'In',
            'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'),
            'status' => 'active',
        ]);
        $employee = Employee::create([
            'employee_no' => 'E-'.uniqid(), 'first_name' => 'Staff',
            'category' => 'office', 'status' => $status, 'user_id' => $user->id,
        ]);

        return [$employee, $user];
    }

    public function test_resigning_an_employee_disables_their_login(): void
    {
        [$employee, $user] = $this->employeeWithLogin();

        $this->service()->update($employee->id, ['status' => 'resigned']);

        $this->assertSame('inactive', $user->fresh()->status, 'A resigned employee must not keep an active login.');
    }

    public function test_reactivating_an_employee_restores_their_login(): void
    {
        [$employee, $user] = $this->employeeWithLogin('resigned');
        $user->update(['status' => 'inactive']);

        $this->service()->update($employee->id, ['status' => 'active']);

        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_a_status_change_is_stamped_with_who_and_when(): void
    {
        [$employee] = $this->employeeWithLogin();
        $actor = User::create(['first_name' => 'HR', 'last_name' => 'Admin', 'email' => 'hr-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x')]);
        $this->actingAs($actor);

        $this->service()->update($employee->id, ['status' => 'inactive']);

        $fresh = $employee->fresh();
        $this->assertNotNull($fresh->status_changed_at);
        $this->assertSame($actor->id, $fresh->status_changed_by);
    }

    public function test_it_does_not_disturb_a_suspended_login(): void
    {
        [$employee, $user] = $this->employeeWithLogin();
        $user->update(['status' => 'suspended']);

        $this->service()->update($employee->id, ['status' => 'resigned']);

        $this->assertSame('suspended', $user->fresh()->status);
    }

    public function test_an_unrelated_edit_does_not_touch_the_login(): void
    {
        [$employee, $user] = $this->employeeWithLogin();

        $this->service()->update($employee->id, ['phone' => '012-3456789']);

        $this->assertSame('active', $user->fresh()->status);
        $this->assertNull($employee->fresh()->status_changed_at);
    }

    public function test_last_working_date_and_reason_are_stored(): void
    {
        [$employee] = $this->employeeWithLogin();

        $this->service()->update($employee->id, [
            'status' => 'resigned',
            'last_working_date' => '2026-08-31',
            'resignation_reason' => 'Migrating overseas',
        ]);

        $fresh = $employee->fresh();
        $this->assertSame('2026-08-31', $fresh->last_working_date->format('Y-m-d'));
        $this->assertSame('Migrating overseas', $fresh->resignation_reason);
    }

    public function test_changing_employee_number_is_audited(): void
    {
        [$employee] = $this->employeeWithLogin();
        $old = $employee->employee_no;
        $actor = User::create(['first_name' => 'HR', 'last_name' => 'X', 'email' => 'hr-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x')]);
        $this->actingAs($actor);

        $this->service()->update($employee->id, ['employee_no' => 'NEW-001']);

        $log = ActivityLog::where('action', 'employee.employee_no_changed')->sole();
        $this->assertSame($old, $log->properties['from']);
        $this->assertSame('NEW-001', $log->properties['to']);
        $this->assertSame($actor->id, $log->user_id);
    }

    public function test_an_edit_that_keeps_the_same_employee_number_is_not_audited(): void
    {
        [$employee] = $this->employeeWithLogin();
        $this->service()->update($employee->id, ['employee_no' => $employee->employee_no, 'phone' => '011']);
        $this->assertSame(0, ActivityLog::where('action', 'employee.employee_no_changed')->count());
    }
}
