<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug B — Managers/Directors given the role any way other than
 * UserService::applyOrgRoleFlags() (e.g. via the User Access page, or an
 * account created before the flags existed) still have is_manager/is_director
 * = false, so LeaveService::authorizeStage() and LeaveController::
 * pendingApprovals() (gated on those columns) reject them with a 403 and hide
 * the pending list.
 *
 * User::isManagerApprover()/isDirectorApprover() now also recognise the
 * "Managers"/"Directors" role directly, independent of the flag.
 */
class LeaveApproverRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function approver(string $role): User
    {
        $user = User::create([
            'first_name' => ucfirst(strtolower($role)),
            'last_name' => 'Approver',
            'email' => strtolower($role).'@mge-pms.test',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);

        // Role assigned directly, NOT through UserService — is_manager /
        // is_director are left false, mirroring the Access-page/legacy path.
        $user->assignRole($role);
        $user->givePermissionTo(['leave.view', 'leave.approve']);

        $this->assertFalse($user->fresh()->is_manager);
        $this->assertFalse($user->fresh()->is_director);

        return $user;
    }

    private function employeeForLeave(): Employee
    {
        $owner = User::create([
            'first_name' => 'Staff',
            'last_name' => 'Member',
            'email' => 'staff-'.uniqid().'@mge-pms.test',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);

        return Employee::create([
            'employee_no' => 'E-'.uniqid(),
            'first_name' => 'Staff',
            'category' => 'office',
            'hire_date' => '2020-01-01',
            'user_id' => $owner->id,
        ]);
    }

    /**
     * A leave type with a designated approver who is NOT the actor, at both
     * stages — so the "no designated approver → any leave.approve holder may
     * act" fallback in authorizeStage() cannot be what lets the test pass.
     * Only the role-based approver check can.
     */
    private function plainUser(): User
    {
        return User::create([
            'first_name' => 'Other',
            'last_name' => 'Approver',
            'email' => 'other-'.uniqid().'@mge-pms.test',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);
    }

    private function leaveTypeWithOtherDesignatedApprovers(): LeaveType
    {
        $otherManager = $this->plainUser();
        $otherDirector = $this->plainUser();

        return LeaveType::create([
            'name' => 'Annual Test',
            'code' => 'ATT-'.uniqid(),
            'default_days_per_year' => 14,
            'is_paid' => true,
            'requires_attachment' => false,
            'is_active' => true,
            'requires_director_approval' => true,
            'manager_approver_id' => $otherManager->id,
            'director_approver_id' => $otherDirector->id,
        ]);
    }

    private function pendingRequest(Employee $employee, LeaveType $type, string $level): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
            'days_count' => 2,
            'status' => 'pending',
            'current_approval_level' => $level,
            'created_by' => $employee->user_id,
        ]);
    }

    // ── Managers role ──

    public function test_a_managers_role_user_sees_a_pending_manager_stage_request(): void
    {
        $manager = $this->approver('Managers');
        $employee = $this->employeeForLeave();
        $type = $this->leaveTypeWithOtherDesignatedApprovers();
        $request = $this->pendingRequest($employee, $type, 'manager');

        $response = $this->actingAs($manager)
            ->getJson('/api/leaves/pending-approvals')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($request->id, $ids);
    }

    public function test_a_managers_role_user_can_approve_a_manager_stage_request(): void
    {
        $manager = $this->approver('Managers');
        $employee = $this->employeeForLeave();
        $type = $this->leaveTypeWithOtherDesignatedApprovers();
        $request = $this->pendingRequest($employee, $type, 'manager');

        $response = $this->actingAs($manager)
            ->postJson("/api/leaves/{$request->id}/approve")
            ->assertOk();

        // requires_director_approval is true, so it advances to the director
        // stage rather than being fully approved.
        $this->assertSame('director', $response->json('data.current_approval_level'));
        $this->assertSame('pending', $response->json('data.status'));
        $this->assertNotNull($response->json('data.manager_approved_by'));
    }

    // ── Directors role ──

    public function test_a_directors_role_user_sees_a_pending_director_stage_request(): void
    {
        $director = $this->approver('Directors');
        $employee = $this->employeeForLeave();
        $type = $this->leaveTypeWithOtherDesignatedApprovers();
        $request = $this->pendingRequest($employee, $type, 'director');

        $response = $this->actingAs($director)
            ->getJson('/api/leaves/pending-approvals')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($request->id, $ids);
    }

    public function test_a_directors_role_user_can_approve_a_director_stage_request(): void
    {
        $director = $this->approver('Directors');
        $employee = $this->employeeForLeave();
        $type = $this->leaveTypeWithOtherDesignatedApprovers();
        $request = $this->pendingRequest($employee, $type, 'director');

        $response = $this->actingAs($director)
            ->postJson("/api/leaves/{$request->id}/approve")
            ->assertOk();

        $this->assertSame('approved', $response->json('data.status'));
        $this->assertNotNull($response->json('data.director_approved_by'));
    }
}
