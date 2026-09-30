<?php

namespace Tests\Feature\Projects;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SiteLogApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private Designation $designation;

    protected function setUp(): void
    {
        parent::setUp();

        config(['notifications.email_enabled' => false]);
        foreach (['projects.view', 'projects.edit'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->department = Department::create(['name' => 'Projects', 'code' => 'PRJ']);
        $this->designation = Designation::create([
            'name' => 'Site Engineer',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
    }

    private function user(string $name, array $permissions = ['projects.view']): User
    {
        $user = User::create([
            'first_name' => $name,
            'last_name' => 'User',
            'email' => strtolower($name).'-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function engineer(string $name, string $employeeNo): User
    {
        $user = $this->user($name);
        Employee::create([
            'employee_no' => $employeeNo,
            'first_name' => $name,
            'last_name' => 'Engineer',
            'department_id' => $this->department->id,
            'designation_id' => $this->designation->id,
            'category' => 'site',
            'status' => 'active',
            'user_id' => $user->id,
        ]);

        return $user;
    }

    public function test_assignment_notifies_new_engineer_without_duplicate_for_unchanged_assignment(): void
    {
        $actor = $this->user('Editor', ['projects.view', 'projects.edit']);
        $firstEngineer = $this->engineer('First', 'SE-001');
        $secondEngineer = $this->engineer('Second', 'SE-002');
        $project = Project::create(['name' => 'Bridge', 'code' => 'BR-1', 'status' => 'in_progress']);

        $response = $this->actingAs($actor)->postJson("/api/projects/{$project->id}/site-logs", [
            'log_date' => now()->toDateString(),
            'work_performed' => 'Piling',
            'site_engineer_id' => $firstEngineer->id,
        ])->assertCreated();

        $logId = $response->json('data.id');
        $this->assertSame('pending', $response->json('data.approval_status'));
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $firstEngineer->id]);

        $this->actingAs($actor)->putJson("/api/projects/{$project->id}/site-logs/{$logId}", [
            'site_engineer_id' => $firstEngineer->id,
        ])->assertOk();
        $this->assertDatabaseCount('notifications', 1);

        $this->actingAs($actor)->putJson("/api/projects/{$project->id}/site-logs/{$logId}", [
            'site_engineer_id' => $secondEngineer->id,
        ])->assertOk()->assertJsonPath('data.approval_status', 'pending');

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $secondEngineer->id]);
    }

    public function test_only_the_assigned_site_engineer_can_approve(): void
    {
        $actor = $this->user('Editor', ['projects.view', 'projects.edit']);
        $assignedEngineer = $this->engineer('Assigned', 'SE-010');
        $otherEngineer = $this->engineer('Other', 'SE-011');
        $project = Project::create(['name' => 'Road', 'code' => 'RD-1', 'status' => 'in_progress']);

        $logId = $this->actingAs($actor)->postJson("/api/projects/{$project->id}/site-logs", [
            'log_date' => now()->toDateString(),
            'work_performed' => 'Earthworks',
            'site_engineer_id' => $assignedEngineer->id,
        ])->assertCreated()->json('data.id');

        $this->actingAs($otherEngineer)
            ->postJson("/api/projects/{$project->id}/site-logs/{$logId}/approve")
            ->assertForbidden();

        $response = $this->actingAs($assignedEngineer)
            ->postJson("/api/projects/{$project->id}/site-logs/{$logId}/approve")
            ->assertOk();

        $this->assertSame('approved', $response->json('data.approval_status'));
        $this->assertSame($assignedEngineer->id, $response->json('data.approved_by'));
        $this->assertNotNull($response->json('data.approved_at'));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'sitelog.approved',
            'subject_id' => $logId,
            'user_id' => $assignedEngineer->id,
        ]);
    }

    public function test_engineer_options_only_include_active_staff_with_site_engineer_designation(): void
    {
        $viewer = $this->user('Viewer');
        $eligible = $this->engineer('Eligible', 'SE-020');
        $otherDesignation = Designation::create([
            'name' => 'Project Manager',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $otherUser = $this->user('Manager');
        Employee::create([
            'employee_no' => 'PM-001',
            'first_name' => 'Manager',
            'designation_id' => $otherDesignation->id,
            'status' => 'active',
            'user_id' => $otherUser->id,
        ]);
        $project = Project::create(['name' => 'Drainage', 'code' => 'DR-1', 'status' => 'in_progress']);

        $response = $this->actingAs($viewer)
            ->getJson("/api/projects/{$project->id}/site-logs/engineers")
            ->assertOk();

        $this->assertSame([$eligible->id], collect($response->json('data'))->pluck('user_id')->all());
    }
}
