<?php

namespace Tests\Feature\Leave;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveStatusNotification;
use App\Services\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Leave is decided by the manager of the employee's own department. A manager or a
 * department without one falls back to the Directors. HR is informed, never the approver.
 */
class DepartmentApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Department $engineering;

    private Department $finance;

    protected function setUp(): void
    {
        parent::setUp();
        config(['leave.engine_enabled' => false]);

        foreach (['dashboard.view', 'leave.view', 'leave.request', 'leave.approve', 'leave.manage', 'training.view'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (['Managers', 'Directors', 'Admin & HR'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->engineering = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $this->finance = Department::create(['name' => 'Finance', 'code' => 'FIN']);
    }

    private function user(string $name, ?Department $department = null, ?string $role = null, array $permissions = ['leave.view'], ?string $designation = null): User
    {
        $user = User::create([
            'first_name' => $name,
            'last_name' => 'User',
            'email' => strtolower($name).'-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('secret'),
            'status' => 'active',
            'department_id' => $department?->id,
            'designation_id' => $designation ? Designation::firstOrCreate(['name' => $designation, 'department_id' => ($department ?? $this->engineering)->id])->id : null,
        ]);
        $role && $user->assignRole($role);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function employeeOf(User $user, ?Department $department = null): Employee
    {
        return Employee::create([
            'employee_no' => 'E-'.uniqid(),
            'first_name' => $user->first_name,
            'last_name' => 'User',
            'category' => 'office',
            'hire_date' => '2020-01-01',
            'user_id' => $user->id,
            'department_id' => $department?->id ?? $user->department_id,
        ]);
    }

    private function leaveType(bool $requiresDirector = false): LeaveType
    {
        return LeaveType::create([
            'name' => 'Annual Test',
            'code' => 'AT-'.uniqid(),
            'default_days_per_year' => 14,
            'is_paid' => true,
            'requires_attachment' => false,
            'is_active' => true,
            'requires_director_approval' => $requiresDirector,
        ]);
    }

    private function pending(Employee $employee, ?LeaveType $type = null): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => ($type ?? $this->leaveType())->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
            'days_count' => 2,
            'status' => 'pending',
            'current_approval_level' => 'manager',
            'created_by' => $employee->user_id,
        ]);
    }

    private function pendingIdsFor(User $user): array
    {
        return collect($this->actingAs($user)->getJson('/api/leaves/pending-approvals')->assertOk()->json('data.data'))
            ->pluck('id')->all();
    }

    public function test_the_manager_of_the_employees_own_department_can_see_and_approve(): void
    {
        $manager = $this->user('EngManager', $this->engineering, 'Managers');
        $staff = $this->employeeOf($this->user('EngStaff', $this->engineering));
        $request = $this->pending($staff);

        $this->assertContains($request->id, $this->pendingIdsFor($manager));

        $this->actingAs($manager)->postJson("/api/leaves/{$request->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_a_manager_of_another_department_can_neither_see_nor_decide(): void
    {
        $financeManager = $this->user('FinManager', $this->finance, 'Managers');
        $staff = $this->employeeOf($this->user('EngStaff', $this->engineering));
        $this->user('EngManager', $this->engineering, 'Managers');
        $request = $this->pending($staff);

        $this->assertNotContains($request->id, $this->pendingIdsFor($financeManager));
        $this->actingAs($financeManager)->postJson("/api/leaves/{$request->id}/approve")->assertForbidden();
        $this->actingAs($financeManager)->postJson("/api/leaves/{$request->id}/reject")->assertForbidden();
        $this->actingAs($financeManager)->getJson("/api/leaves/{$request->id}")->assertNotFound();
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_hr_cannot_approve_or_reject_even_with_every_leave_permission(): void
    {
        $hr = $this->user('Hr', $this->engineering, 'Admin & HR', ['leave.view', 'leave.approve', 'leave.manage']);
        $this->user('EngManager', $this->engineering, 'Managers');
        $request = $this->pending($this->employeeOf($this->user('EngStaff', $this->engineering)));

        $this->assertNotContains($request->id, $this->pendingIdsFor($hr));
        $this->actingAs($hr)->postJson("/api/leaves/{$request->id}/approve")->assertForbidden();
        $this->actingAs($hr)->postJson("/api/leaves/{$request->id}/reject")->assertForbidden();
        $this->assertSame('pending', $request->fresh()->status);

        // ...but HR can still read it for its records.
        $this->actingAs($hr)->getJson("/api/leaves/{$request->id}")->assertOk();
    }

    public function test_a_managers_own_leave_goes_to_the_directors_not_a_peer_manager(): void
    {
        $manager = $this->user('EngManager', $this->engineering, 'Managers');
        $peer = $this->user('EngPeer', $this->engineering, 'Managers');
        $director = $this->user('Director', null, 'Directors');
        $request = $this->pending($this->employeeOf($manager));

        $this->assertNotContains($request->id, $this->pendingIdsFor($peer));
        $this->actingAs($peer)->postJson("/api/leaves/{$request->id}/approve")->assertForbidden();

        $this->assertContains($request->id, $this->pendingIdsFor($director));
        $this->actingAs($director)->postJson("/api/leaves/{$request->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_a_department_without_a_manager_falls_back_to_the_directors(): void
    {
        $director = $this->user('Director', null, 'Directors');
        $financeManager = $this->user('FinManager', $this->finance, 'Managers');
        $request = $this->pending($this->employeeOf($this->user('EngStaff', $this->engineering)));

        $this->assertContains($request->id, $this->pendingIdsFor($director));
        $this->assertNotContains($request->id, $this->pendingIdsFor($financeManager));
    }

    public function test_directors_do_not_see_staff_requests_while_their_department_has_a_manager(): void
    {
        $director = $this->user('Director', null, 'Directors');
        $this->user('EngManager', $this->engineering, 'Managers');
        $request = $this->pending($this->employeeOf($this->user('EngStaff', $this->engineering)));

        $this->assertNotContains($request->id, $this->pendingIdsFor($director));
        $this->actingAs($director)->postJson("/api/leaves/{$request->id}/approve")->assertForbidden();
    }

    public function test_a_manager_cannot_approve_their_own_request(): void
    {
        $manager = $this->user('EngManager', $this->engineering, 'Managers');
        $request = $this->pending($this->employeeOf($manager));

        $this->actingAs($manager)->postJson("/api/leaves/{$request->id}/approve")->assertForbidden();
    }

    public function test_a_directors_decision_at_the_manager_stage_is_final(): void
    {
        $director = $this->user('Director', null, 'Directors');
        $request = $this->pending($this->employeeOf($this->user('EngStaff', $this->engineering)), $this->leaveType(requiresDirector: true));

        $this->actingAs($director)->postJson("/api/leaves/{$request->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_a_department_manager_can_read_a_subordinates_request_but_not_another_departments(): void
    {
        $manager = $this->user('EngManager', $this->engineering, 'Managers');
        $own = $this->pending($this->employeeOf($this->user('EngStaff', $this->engineering)));
        $foreign = $this->pending($this->employeeOf($this->user('FinStaff', $this->finance)));

        $this->actingAs($manager)->getJson("/api/leaves/{$own->id}")->assertOk();
        $this->actingAs($manager)->getJson("/api/leaves/{$foreign->id}")->assertNotFound();
    }

    public function test_the_department_manager_is_asked_to_act_and_hr_is_only_informed(): void
    {
        Notification::fake();
        $manager = $this->user('EngManager', $this->engineering, 'Managers');
        $otherManager = $this->user('FinManager', $this->finance, 'Managers');
        $hr = $this->user('Hr', null, null, ['leave.view', 'leave.manage']);
        $staff = $this->employeeOf($this->user('EngStaff', $this->engineering));

        $request = app(LeaveService::class)->apply([
            'employee_id' => $staff->id,
            'leave_type_id' => $this->leaveType()->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
        ], $staff->user_id);

        Notification::assertSentTo($manager, LeaveStatusNotification::class, fn ($n) => $n->toArray($manager)['action_type'] === 'leave_submitted'
            && $n->toArray($manager)['link'] === '/hr/leave/approvals');
        Notification::assertNotSentTo($otherManager, LeaveStatusNotification::class);
        Notification::assertSentTo($hr, LeaveStatusNotification::class, fn ($n) => $n->toArray($hr)['action_type'] === 'leave_info_submitted'
            && $n->toArray($hr)['link'] === '/hr/leave');

        $this->actingAs($manager)->postJson("/api/leaves/{$request->id}/approve")->assertOk();

        Notification::assertSentTo($hr, LeaveStatusNotification::class, fn ($n) => $n->toArray($hr)['action_type'] === 'leave_info_approved');
        Notification::assertSentTo($staff->user, LeaveStatusNotification::class, fn ($n) => $n->toArray($staff->user)['action_type'] === 'leave_approved');
    }

    public function test_the_approvals_count_only_covers_what_the_viewer_can_decide_and_hr_is_not_an_approver(): void
    {
        $manager = $this->user('EngManager', $this->engineering, 'Managers');
        $hr = $this->user('Hr', null, null, ['leave.view', 'leave.manage']);
        $mine = $this->pending($this->employeeOf($this->user('EngStaff', $this->engineering)));
        $this->pending($this->employeeOf($this->user('FinStaff', $this->finance)));

        $service = app(LeaveService::class);

        // The dashboard "Leave to Approve" card is built from these two calls.
        $this->assertTrue($service->isLeaveApprover($manager));
        $this->assertSame([$mine->id], $service->pendingApprovalIdsFor($manager));

        $this->assertFalse($service->isLeaveApprover($hr));
        $this->assertSame([], $service->pendingApprovalIdsFor($hr));
    }

    public function test_the_audit_command_flags_departments_without_a_manager_and_staff_without_a_department(): void
    {
        $this->user('EngManager', $this->engineering, 'Managers');
        $this->user('Director', null, 'Directors');
        $this->employeeOf($this->user('EngStaff', $this->engineering));
        $this->employeeOf($this->user('FinStaff', $this->finance));
        $this->employeeOf($this->user('Floating'));

        $this->artisan('approvals:audit')
            ->expectsOutputToContain('Directors: 1 | Managers: 1')
            ->expectsOutputToContain('NONE -> Project Manager / Directors decide')
            ->expectsOutputToContain('1 staff record(s) have no department')
            ->assertSuccessful();
    }

    // ── Project Manager (designation) ──

    public function test_a_project_manager_decides_the_department_heads_leave_but_not_another_offices(): void
    {
        $head = $this->user('EngHead', $this->engineering, 'Managers');
        $pm = $this->user('EngPm', $this->engineering, null, ['leave.view'], 'Project Manager');
        $otherPm = $this->user('FinPm', $this->finance, null, ['leave.view'], 'Project Manager');
        $request = $this->pending($this->employeeOf($head));

        $this->assertContains($request->id, $this->pendingIdsFor($pm));
        $this->assertNotContains($request->id, $this->pendingIdsFor($otherPm));
        $this->actingAs($otherPm)->postJson("/api/leaves/{$request->id}/approve")->assertForbidden();

        $this->actingAs($pm)->postJson("/api/leaves/{$request->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_a_project_manager_decides_staff_leave_when_the_department_has_no_head(): void
    {
        $pm = $this->user('EngPm', $this->engineering, null, ['leave.view'], 'Project Manager');
        $request = $this->pending($this->employeeOf($this->user('EngStaff', $this->engineering)));

        $this->assertContains($request->id, $this->pendingIdsFor($pm));
        $this->actingAs($pm)->postJson("/api/leaves/{$request->id}/reject")->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    public function test_a_project_manager_does_not_decide_staff_leave_while_the_department_has_a_head(): void
    {
        $this->user('EngHead', $this->engineering, 'Managers');
        $pm = $this->user('EngPm', $this->engineering, null, ['leave.view'], 'Project Manager');
        $request = $this->pending($this->employeeOf($this->user('EngStaff', $this->engineering)));

        $this->assertNotContains($request->id, $this->pendingIdsFor($pm));
        $this->actingAs($pm)->postJson("/api/leaves/{$request->id}/approve")->assertForbidden();
    }

    public function test_directors_can_still_decide_the_heads_leave_alongside_the_project_manager(): void
    {
        $head = $this->user('EngHead', $this->engineering, 'Managers');
        $this->user('EngPm', $this->engineering, null, ['leave.view'], 'Project Manager');
        $director = $this->user('Director', null, 'Directors');
        $request = $this->pending($this->employeeOf($head));

        $this->assertContains($request->id, $this->pendingIdsFor($director));
    }

    public function test_a_project_manager_is_recognised_from_the_hr_staff_record_designation(): void
    {
        $pmUser = $this->user('EngPm', $this->engineering);
        $this->employeeOf($pmUser)->update(['designation_id' => Designation::firstOrCreate(['name' => 'Project Manager', 'department_id' => $this->engineering->id])->id]);
        $request = $this->pending($this->employeeOf($this->user('EngStaff', $this->engineering)));

        $this->assertContains($request->id, $this->pendingIdsFor($pmUser));
    }

    public function test_a_project_managers_own_leave_is_not_decided_by_themselves(): void
    {
        $pm = $this->user('EngPm', $this->engineering, null, ['leave.view'], 'Project Manager');
        $peerPm = $this->user('EngPm2', $this->engineering, null, ['leave.view'], 'Project Manager');
        $request = $this->pending($this->employeeOf($pm));

        $this->actingAs($pm)->postJson("/api/leaves/{$request->id}/approve")->assertForbidden();
        $this->assertContains($request->id, $this->pendingIdsFor($peerPm));
    }

    public function test_setting_the_project_manager_designation_grants_access_to_the_approval_pages(): void
    {
        $user = $this->user('Future', $this->engineering, null, []);
        $this->assertFalse($user->can('leave.approve'));

        app(\App\Services\UserService::class)->updateUser($user->id, ['designation_id' => Designation::firstOrCreate(['name' => 'Project Manager', 'department_id' => $this->engineering->id])->id]);

        $user = $user->fresh();
        $this->assertTrue($user->hasPermissionTo('leave.view'));
        $this->assertTrue($user->hasPermissionTo('leave.approve'));
        $this->assertTrue($user->hasPermissionTo('training.view'));
    }

    public function test_the_backfill_migration_gives_existing_project_managers_the_approval_page_access(): void
    {
        $byAccount = $this->user('ByAccount', $this->engineering, null, [], 'Project Manager');
        $byStaffRecord = $this->user('ByStaff', $this->engineering, null, []);
        $this->employeeOf($byStaffRecord)->update(['designation_id' => Designation::firstOrCreate(['name' => 'Project Manager', 'department_id' => $this->engineering->id])->id]);
        $bystander = $this->user('Bystander', $this->engineering, null, []);

        (require database_path('migrations/2026_10_16_000001_grant_leave_training_access_to_project_managers.php'))->up();

        foreach ([$byAccount, $byStaffRecord] as $pm) {
            $this->assertTrue($pm->fresh()->hasPermissionTo('leave.approve'));
            $this->assertTrue($pm->fresh()->hasPermissionTo('training.view'));
        }
        $this->assertFalse($bystander->fresh()->hasPermissionTo('leave.approve'));
    }

    public function test_the_page_access_is_withdrawn_when_the_designation_changes_away_from_project_manager(): void
    {
        Role::findOrCreate('Employee', 'web');
        $service = app(\App\Services\UserService::class);
        $user = $this->user('Moving', $this->engineering, 'Employee', []);

        $service->updateUser($user->id, ['designation_id' => Designation::firstOrCreate(['name' => 'Project Manager', 'department_id' => $this->engineering->id])->id]);
        $this->assertTrue($user->fresh()->hasPermissionTo('leave.approve'));

        $service->updateUser($user->id, ['designation_id' => Designation::firstOrCreate(['name' => 'Engineer', 'department_id' => $this->engineering->id])->id]);

        $user = $user->fresh();
        $this->assertFalse($user->hasPermissionTo('leave.approve'));
        $this->assertFalse($user->hasPermissionTo('training.view'));
        $this->assertTrue($user->hasPermissionTo('leave.view'), 'leave.view is part of the Employee template and must stay');
    }
}
