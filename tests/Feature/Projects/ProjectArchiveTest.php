<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Archiving hides a project from the default/status-filtered listing without
 * touching the record itself. It is request-scoped filtering in the
 * repository's getWithRelations(), not a global scope — so direct lookups
 * (show, relations) must keep working normally for an archived project.
 */
class ProjectArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit', 'projects.delete'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function actor(array $permissions = ['projects.view', 'projects.edit']): User
    {
        $u = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);
        $u->givePermissionTo($permissions);

        return $u;
    }

    private function project(array $overrides = []): Project
    {
        return Project::create(array_merge([
            'name' => 'P',
            'code' => 'PR'.random_int(1000, 9999),
            'status' => 'in_progress',
        ], $overrides));
    }

    public function test_archive_hides_project_from_default_listing(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/archive")->assertOk();

        $this->assertNotNull($project->fresh()->archived_at);

        $response = $this->actingAs($actor)->getJson('/api/projects');
        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertNotContains($project->id, $ids);
    }

    public function test_archived_project_appears_under_archived_filter(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/archive")->assertOk();

        $response = $this->actingAs($actor)->getJson('/api/projects?status=archived');
        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($project->id, $ids);
    }

    public function test_archived_project_disappears_from_its_original_status_filter(): void
    {
        $actor = $this->actor();
        $project = $this->project(['status' => 'in_progress']);

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/archive")->assertOk();

        $response = $this->actingAs($actor)->getJson('/api/projects?status=in_progress');
        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertNotContains($project->id, $ids);
    }

    public function test_unarchive_restores_project_to_default_listing(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/archive")->assertOk();
        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/unarchive")->assertOk();

        $this->assertNull($project->fresh()->archived_at);

        $response = $this->actingAs($actor)->getJson('/api/projects');
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($project->id, $ids);
    }

    public function test_archive_requires_projects_edit_permission(): void
    {
        $actor = $this->actor(permissions: []);
        $project = $this->project();

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/archive")->assertForbidden();
    }

    public function test_unarchive_requires_projects_edit_permission(): void
    {
        $project = $this->project();
        $project->forceFill(['archived_at' => now()])->save();

        $noPermActor = $this->actor(permissions: []);
        $this->actingAs($noPermActor)->postJson("/api/projects/{$project->id}/unarchive")->assertForbidden();
    }

    public function test_show_and_direct_lookup_still_work_for_archived_project(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/archive")->assertOk();

        $this->actingAs($actor)->getJson("/api/projects/{$project->id}")->assertOk();

        // Direct model lookup / relation traversal is unaffected by archiving —
        // no global scope is involved.
        $this->assertNotNull(Project::find($project->id));
    }
}
