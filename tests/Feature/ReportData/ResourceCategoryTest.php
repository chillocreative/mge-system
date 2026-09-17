<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectResourceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ResourceCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    public function test_project_list_replaces_defaults_for_site_log_validation(): void
    {
        $project = $this->project();

        // Default list still accepted while the project has no categories.
        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/site-logs", [
            'log_date' => '2026-01-05', 'workers' => [['worker_type' => 'General Worker', 'count' => 30]],
        ])->assertCreated();

        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/resource-categories?kind=worker", ['rows' => [
            ['group' => 'Management Team', 'name' => 'Project Manager', 'sort_order' => 0],
            ['group' => 'Tradesman', 'name' => 'China Worker', 'sort_order' => 1],
        ]])->assertOk();

        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/site-logs", [
            'log_date' => '2026-01-06', 'workers' => [['worker_type' => 'China Worker', 'count' => 8]],
        ])->assertCreated();

        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/site-logs", [
            'log_date' => '2026-01-07', 'workers' => [['worker_type' => 'General Worker', 'count' => 1]],
        ])->assertStatus(422);
    }

    public function test_a_category_name_containing_a_comma_is_validated_correctly(): void
    {
        $project = $this->project();

        $this->actingAs($this->editor)->putJson("/api/projects/{$project->id}/resource-categories?kind=worker", ['rows' => [
            ['group' => 'Tradesman', 'name' => 'Site Clerk, DC', 'sort_order' => 0],
        ]])->assertOk();

        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/site-logs", [
            'log_date' => '2026-01-08', 'workers' => [['worker_type' => 'Site Clerk, DC', 'count' => 1]],
        ])->assertCreated();

        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/site-logs", [
            'log_date' => '2026-01-09', 'workers' => [['worker_type' => 'Site Clerk', 'count' => 1]],
        ])->assertStatus(422);
    }

    public function test_seed_defaults_copies_global_lists(): void
    {
        $project = $this->project();
        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/resource-categories/seed-defaults?kind=machinery")->assertOk();
        $this->assertTrue(ProjectResourceCategory::where('project_id', $project->id)->where('kind', 'machinery')->where('name', 'Excavator')->exists());
    }
}
