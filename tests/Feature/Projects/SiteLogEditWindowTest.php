<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\SiteLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciri 20 — a site log locks for editing after its window, with an elevated
 * bypass, and edits are audited.
 */
class SiteLogEditWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit', 'projects.delete'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        config(['sitelogs.edit_window_days' => 7]);
    }

    private function user(array $perms): User
    {
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo($perms);

        return $u;
    }

    private function log(string $date): array
    {
        $creator = User::create(['first_name' => 'C', 'last_name' => 'R', 'email' => 'c-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $project = Project::create(['name' => 'P', 'code' => 'P1', 'status' => 'in_progress']);
        $log = SiteLog::create(['project_id' => $project->id, 'log_date' => $date, 'work_performed' => 'x', 'logged_by' => $creator->id]);

        return [$project, $log];
    }

    public function test_a_recent_log_can_be_edited(): void
    {
        [$project, $log] = $this->log(now()->subDays(2)->toDateString());

        $this->actingAs($this->user(['projects.view', 'projects.edit']))
            ->putJson("/api/projects/{$project->id}/site-logs/{$log->id}", ['work_performed' => 'updated'])
            ->assertOk();
    }

    public function test_an_old_log_is_locked_for_a_normal_editor(): void
    {
        [$project, $log] = $this->log(now()->subDays(30)->toDateString());

        $this->actingAs($this->user(['projects.view', 'projects.edit']))
            ->putJson("/api/projects/{$project->id}/site-logs/{$log->id}", ['work_performed' => 'late edit'])
            ->assertStatus(422);
    }

    public function test_an_elevated_user_can_still_edit_an_old_log(): void
    {
        [$project, $log] = $this->log(now()->subDays(30)->toDateString());

        $this->actingAs($this->user(['projects.view', 'projects.edit', 'projects.delete']))
            ->putJson("/api/projects/{$project->id}/site-logs/{$log->id}", ['work_performed' => 'correction'])
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', ['action' => 'sitelog.updated', 'subject_id' => $log->id]);
    }

    public function test_window_of_zero_means_always_editable(): void
    {
        config(['sitelogs.edit_window_days' => 0]);
        [$project, $log] = $this->log(now()->subDays(90)->toDateString());

        $this->actingAs($this->user(['projects.view', 'projects.edit']))
            ->putJson("/api/projects/{$project->id}/site-logs/{$log->id}", ['work_performed' => 'ok'])
            ->assertOk();
    }
}
