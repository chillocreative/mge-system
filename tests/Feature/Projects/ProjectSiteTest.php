<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\ProjectSite;
use App\Models\SiteLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciri 25 foundation — multiple sites per project, and operational records
 * optionally filed under a site (additively — free-text location still works).
 */
class ProjectSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function user(array $perms): User
    {
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo($perms);

        return $u;
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    public function test_sites_can_be_created_and_are_scoped_to_a_project(): void
    {
        $editor = $this->user(['projects.view', 'projects.edit']);
        $p1 = $this->project();
        $p2 = $this->project();

        $this->actingAs($editor)->postJson('/api/project-sites', ['project_id' => $p1->id, 'name' => 'Block A'])->assertCreated();
        $this->actingAs($editor)->postJson('/api/project-sites', ['project_id' => $p2->id, 'name' => 'Block B'])->assertCreated();

        $res = $this->actingAs($editor)->getJson("/api/project-sites?project_id={$p1->id}")->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Block A', $res->json('data.0.name'));
    }

    public function test_a_site_log_can_be_filed_under_a_site(): void
    {
        $editor = $this->user(['projects.view', 'projects.edit']);
        $project = $this->project();
        $site = ProjectSite::create(['project_id' => $project->id, 'name' => 'Zone 1']);

        $res = $this->actingAs($editor)
            ->postJson("/api/projects/{$project->id}/site-logs", ['log_date' => now()->toDateString(), 'site_id' => $site->id, 'work_performed' => 'x'])
            ->assertCreated();

        $this->assertSame($site->id, SiteLog::first()->site_id);
    }

    public function test_a_site_from_another_project_is_rejected_on_a_site_log(): void
    {
        $editor = $this->user(['projects.view', 'projects.edit']);
        $project = $this->project();
        $otherSite = ProjectSite::create(['project_id' => $this->project()->id, 'name' => 'Foreign']);

        $this->actingAs($editor)
            ->postJson("/api/projects/{$project->id}/site-logs", ['log_date' => now()->toDateString(), 'site_id' => $otherSite->id, 'work_performed' => 'x'])
            ->assertStatus(422);

        $this->assertSame(0, SiteLog::count());
    }

    public function test_deleting_a_site_keeps_the_records_and_nulls_the_link(): void
    {
        $editor = $this->user(['projects.view', 'projects.edit']);
        $project = $this->project();
        $site = ProjectSite::create(['project_id' => $project->id, 'name' => 'Zone 1']);
        $log = SiteLog::create(['project_id' => $project->id, 'site_id' => $site->id, 'log_date' => now()->toDateString(), 'work_performed' => 'x', 'logged_by' => $editor->id]);

        $this->actingAs($editor)->deleteJson("/api/project-sites/{$site->id}")->assertOk();

        $this->assertDatabaseHas('site_logs', ['id' => $log->id]); // record survives
        $this->assertNull($log->fresh()->site_id);                  // link is nulled
    }
}
