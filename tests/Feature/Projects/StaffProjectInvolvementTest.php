<?php

namespace Tests\Feature\Projects;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ciri 15 — a staff member's project involvement, via project_members.
 */
class StaffProjectInvolvementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_projects_the_employees_login_is_a_member_of(): void
    {
        $user = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x')]);
        $employee = Employee::create(['employee_no' => 'E1', 'first_name' => 'A', 'category' => 'office', 'user_id' => $user->id]);
        $p1 = Project::create(['name' => 'Bridge', 'code' => 'BRG', 'status' => 'in_progress']);
        $p2 = Project::create(['name' => 'Drainage', 'code' => 'DRN', 'status' => 'completed']);
        ProjectMember::create(['project_id' => $p1->id, 'user_id' => $user->id, 'role' => 'engineer']);
        ProjectMember::create(['project_id' => $p2->id, 'user_id' => $user->id, 'role' => 'member', 'left_at' => '2026-06-01']);

        $projects = app(EmployeeService::class)->projectsFor($employee->id);

        $this->assertCount(2, $projects);
        $bridge = $projects->firstWhere('project_id', $p1->id);
        $this->assertSame('engineer', $bridge['role']);
        $this->assertTrue($bridge['active']);
        $this->assertFalse($projects->firstWhere('project_id', $p2->id)['active']);
    }

    public function test_an_employee_with_no_login_has_no_projects(): void
    {
        $employee = Employee::create(['employee_no' => 'E2', 'first_name' => 'A', 'category' => 'office']);
        $this->assertCount(0, app(EmployeeService::class)->projectsFor($employee->id));
    }
}
