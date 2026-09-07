<?php

namespace Tests\Feature\Safety;

use App\Models\Project;
use App\Models\ProjectSite;
use App\Models\SafetyIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A safety incident can be filed under a site, but only one belonging to the
 * incident's own project (cross-project site association is rejected).
 */
class SafetyIncidentSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['safety.view', 'safety.create', 'safety.manage'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function actor(): User
    {
        $u = User::create(['first_name' => 'S', 'last_name' => 'O', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo(['safety.create']);

        return $u;
    }

    private function payload(int $projectId, array $over = []): array
    {
        return array_merge([
            'project_id' => $projectId, 'title' => 'Fall', 'description' => 'd',
            'incident_date' => now()->toDateString(), 'severity' => 'minor', 'type' => 'injury',
        ], $over);
    }

    public function test_incident_can_be_filed_under_its_own_projects_site(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'PS'.random_int(1000, 9999), 'status' => 'in_progress']);
        $site = ProjectSite::create(['project_id' => $project->id, 'name' => 'Zone 1']);

        $this->actingAs($this->actor())
            ->postJson('/api/safety/incidents', $this->payload($project->id, ['site_id' => $site->id]))
            ->assertCreated();

        $this->assertSame($site->id, SafetyIncident::first()->site_id);
    }

    public function test_a_site_from_another_project_is_rejected(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'PS'.random_int(1000, 9999), 'status' => 'in_progress']);
        $foreign = ProjectSite::create(['project_id' => Project::create(['name' => 'Q', 'code' => 'QS'.random_int(1000, 9999), 'status' => 'in_progress'])->id, 'name' => 'Foreign']);

        $this->actingAs($this->actor())
            ->postJson('/api/safety/incidents', $this->payload($project->id, ['site_id' => $foreign->id]))
            ->assertStatus(422);

        $this->assertSame(0, SafetyIncident::count());
    }
}
