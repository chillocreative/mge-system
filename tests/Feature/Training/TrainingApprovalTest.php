<?php

namespace Tests\Feature\Training;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\TrainingRequest;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Training is decided by the manager of the requester's own department (Directors when the
 * requester is a manager or the department has none). HR is informed and sees every request
 * but never decides.
 */
class TrainingApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Department $engineering;

    private Department $finance;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['training.view', 'training.request', 'training.manage', 'training.approve'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (['Managers', 'Directors', 'Admin & HR'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->engineering = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $this->finance = Department::create(['name' => 'Finance', 'code' => 'FIN']);
    }

    private function user(string $name, ?Department $department = null, ?string $role = null, array $permissions = ['training.view', 'training.request'], ?string $designation = null): User
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

    private function request(User $requester, string $title = 'Safety Course'): TrainingRequest
    {
        $employee = Employee::create([
            'employee_no' => 'E-'.uniqid(),
            'first_name' => $requester->first_name,
            'last_name' => 'User',
            'category' => 'office',
            'user_id' => $requester->id,
            'department_id' => $requester->department_id,
        ]);

        return TrainingRequest::create([
            'employee_id' => $employee->id,
            'title' => $title,
            'status' => 'pending',
            'created_by' => $requester->id,
        ]);
    }

    private function listedIds(User $viewer): array
    {
        return collect($this->actingAs($viewer)->getJson('/api/training/requests')->assertOk()->json('data.data'))
            ->pluck('id')->all();
    }

    public function test_the_department_manager_can_approve_and_the_requester_is_told(): void
    {
        Notification::fake();
        $manager = $this->user('EngManager', $this->engineering, 'Managers');
        $staff = $this->user('EngStaff', $this->engineering);
        $request = $this->request($staff);

        $this->actingAs($manager)->postJson("/api/training/requests/{$request->id}/approve", ['review_notes' => 'Go ahead'])
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertSame($manager->id, $request->fresh()->reviewed_by);
        Notification::assertSentTo($staff, SystemNotification::class, fn ($n) => $n->toArray($staff)['title'] === 'Training request approved');
    }

    public function test_a_manager_of_another_department_cannot_see_or_decide(): void
    {
        $financeManager = $this->user('FinManager', $this->finance, 'Managers');
        $this->user('EngManager', $this->engineering, 'Managers');
        $request = $this->request($this->user('EngStaff', $this->engineering));

        $this->assertNotContains($request->id, $this->listedIds($financeManager));
        $this->actingAs($financeManager)->postJson("/api/training/requests/{$request->id}/approve")->assertForbidden();
        $this->actingAs($financeManager)->postJson("/api/training/requests/{$request->id}/reject")->assertForbidden();
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_hr_sees_every_request_but_cannot_decide_any_of_them(): void
    {
        $hr = $this->user('Hr', null, 'Admin & HR', ['training.view', 'training.manage', 'training.approve']);
        $this->user('EngManager', $this->engineering, 'Managers');
        $request = $this->request($this->user('EngStaff', $this->engineering));

        $rows = collect($this->actingAs($hr)->getJson('/api/training/requests')->assertOk()->json('data.data'));
        $this->assertContains($request->id, $rows->pluck('id')->all());
        $this->assertFalse($rows->firstWhere('id', $request->id)['can_decide']);

        $this->actingAs($hr)->postJson("/api/training/requests/{$request->id}/approve")->assertForbidden();
        $this->actingAs($hr)->postJson("/api/training/requests/{$request->id}/reject")->assertForbidden();
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_the_manager_sees_a_decide_flag_only_on_requests_they_can_decide(): void
    {
        $manager = $this->user('EngManager', $this->engineering, 'Managers');
        $request = $this->request($this->user('EngStaff', $this->engineering));
        $this->request($this->user('FinStaff', $this->finance));

        $rows = collect($this->actingAs($manager)->getJson('/api/training/requests')->assertOk()->json('data.data'));

        $this->assertSame([$request->id], $rows->pluck('id')->all());
        $this->assertTrue($rows->first()['can_decide']);
    }

    public function test_a_managers_own_request_goes_to_the_directors(): void
    {
        $manager = $this->user('EngManager', $this->engineering, 'Managers');
        $peer = $this->user('EngPeer', $this->engineering, 'Managers');
        $director = $this->user('Director', null, 'Directors');
        $request = $this->request($manager);

        $this->actingAs($manager)->postJson("/api/training/requests/{$request->id}/approve")->assertForbidden();
        $this->actingAs($peer)->postJson("/api/training/requests/{$request->id}/approve")->assertForbidden();
        $this->actingAs($director)->postJson("/api/training/requests/{$request->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_a_department_without_a_manager_falls_back_to_the_directors(): void
    {
        $director = $this->user('Director', null, 'Directors');
        $request = $this->request($this->user('EngStaff', $this->engineering));

        $this->assertContains($request->id, $this->listedIds($director));
        $this->actingAs($director)->postJson("/api/training/requests/{$request->id}/reject", ['review_notes' => 'Not now'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    public function test_hr_is_informed_when_a_request_is_decided(): void
    {
        Notification::fake();
        $manager = $this->user('EngManager', $this->engineering, 'Managers');
        $hr = $this->user('Hr', null, null, ['training.view', 'training.manage']);
        $request = $this->request($this->user('EngStaff', $this->engineering));

        $this->actingAs($manager)->postJson("/api/training/requests/{$request->id}/approve")->assertOk();

        Notification::assertSentTo($hr, SystemNotification::class, fn ($n) => $n->toArray($hr)['title'] === 'Training request approved');
    }

    public function test_an_already_decided_request_cannot_be_decided_again(): void
    {
        $manager = $this->user('EngManager', $this->engineering, 'Managers');
        $request = $this->request($this->user('EngStaff', $this->engineering));

        $this->actingAs($manager)->postJson("/api/training/requests/{$request->id}/approve")->assertOk();
        $this->actingAs($manager)->postJson("/api/training/requests/{$request->id}/reject")->assertStatus(422);
    }

    public function test_a_project_manager_decides_the_heads_training_request_and_staff_ones_when_there_is_no_head(): void
    {
        $head = $this->user('EngHead', $this->engineering, 'Managers');
        $pm = $this->user('EngPm', $this->engineering, null, ['training.view', 'training.request'], 'Project Manager');
        $headRequest = $this->request($head);

        $this->assertContains($headRequest->id, $this->listedIds($pm));
        $this->actingAs($pm)->postJson("/api/training/requests/{$headRequest->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        // A department that has no head: the Project Manager decides its staff.
        $financePm = $this->user('FinPm', $this->finance, null, ['training.view', 'training.request'], 'Project Manager');
        $staffRequest = $this->request($this->user('FinStaff', $this->finance));
        $this->actingAs($financePm)->postJson("/api/training/requests/{$staffRequest->id}/reject")->assertOk();

        // ...but not while the department has a head.
        $engRequest = $this->request($this->user('EngStaff', $this->engineering));
        $this->actingAs($pm)->postJson("/api/training/requests/{$engRequest->id}/approve")->assertForbidden();
    }
}
