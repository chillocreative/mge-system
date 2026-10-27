<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectResourceCategory;
use App\Models\User;
use App\Services\ReportData\ResourceCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A site engineer needs a machinery type that is not in the project's list (e.g. jet grouting
 * equipment). The site log form adds it in place, instead of forcing "Other" with a made-up quantity.
 */
class ProjectResourceCategoryAddTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function user(array $permissions): User
    {
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => uniqid().'@test.local', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo($permissions);

        return $u;
    }

    private function editor(): User
    {
        return $this->user(['projects.view', 'projects.edit']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'Flood Mitigation', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    private function add(User $user, Project $project, array $payload)
    {
        return $this->actingAs($user)->postJson("/api/projects/{$project->id}/resource-categories/add", $payload);
    }

    private function names(Project $project, string $kind = 'machinery'): array
    {
        return app(ResourceCategoryService::class)->namesFor($project->id, $kind);
    }

    public function test_new_type_is_added_and_the_built_in_types_are_kept(): void
    {
        $project = $this->project();
        $builtIns = ResourceCategoryService::DEFAULT_MACHINERY;

        $response = $this->add($this->editor(), $project, ['kind' => 'machinery', 'name' => 'Jet Grouting Machine'])->assertOk();

        $response->assertJsonPath('data.name', 'Jet Grouting Machine');
        $this->assertSame([...$builtIns, 'Jet Grouting Machine'], $response->json('data.effective'));
        $this->assertSame([...$builtIns, 'Jet Grouting Machine'], $this->names($project));
    }

    public function test_a_log_can_use_the_new_type_only_after_it_has_been_added(): void
    {
        $project = $this->project();
        $editor = $this->editor();
        $log = ['log_date' => '2026-10-27', 'machinery' => [['machinery_type' => 'Jet Grouting Machine', 'quantity' => 3]]];

        $this->actingAs($editor)->postJson("/api/projects/{$project->id}/site-logs", $log)
            ->assertUnprocessable()->assertJsonValidationErrors('machinery.0.machinery_type');

        $this->add($editor, $project, ['kind' => 'machinery', 'name' => 'Jet Grouting Machine'])->assertOk();

        $this->actingAs($editor)->postJson("/api/projects/{$project->id}/site-logs", $log)->assertCreated();
        $this->assertDatabaseHas('site_log_machinery', ['machinery_type' => 'Jet Grouting Machine', 'quantity' => 3]);
    }

    public function test_an_existing_name_is_reused_in_any_letter_case(): void
    {
        $project = $this->project();
        $editor = $this->editor();

        $this->add($editor, $project, ['kind' => 'machinery', 'name' => 'Piling Rig'])->assertOk();
        $before = ProjectResourceCategory::where('project_id', $project->id)->count();

        $this->add($editor, $project, ['kind' => 'machinery', 'name' => 'piling rig'])
            ->assertOk()->assertJsonPath('data.name', 'Piling Rig');
        $this->add($editor, $project, ['kind' => 'machinery', 'name' => 'excavator'])
            ->assertOk()->assertJsonPath('data.name', 'Excavator');

        $this->assertSame($before, ProjectResourceCategory::where('project_id', $project->id)->count());
    }

    public function test_a_deactivated_type_is_switched_back_on(): void
    {
        $project = $this->project();
        $service = app(ResourceCategoryService::class);
        $service->seedDefaults($project->id, 'machinery');
        ProjectResourceCategory::where('project_id', $project->id)->where('name', 'Crane')->update(['active' => false]);
        $this->assertNotContains('Crane', $this->names($project));

        $this->add($this->editor(), $project, ['kind' => 'machinery', 'name' => 'crane'])
            ->assertOk()->assertJsonPath('data.name', 'Crane');

        $this->assertContains('Crane', $this->names($project));
    }

    public function test_names_are_trimmed_and_inner_spaces_collapsed(): void
    {
        $project = $this->project();

        $this->add($this->editor(), $project, ['kind' => 'machinery', 'name' => "  Grout   Mixer \t Pump  "])
            ->assertOk()->assertJsonPath('data.name', 'Grout Mixer Pump');
    }

    public function test_a_project_with_its_own_list_is_not_reset_to_the_built_ins(): void
    {
        $project = $this->project();
        foreach (['Crane', 'Excavator'] as $i => $name) {
            ProjectResourceCategory::create(['project_id' => $project->id, 'kind' => 'machinery', 'name' => $name, 'sort_order' => $i, 'active' => true]);
        }

        $this->add($this->editor(), $project, ['kind' => 'machinery', 'name' => 'Piling Rig'])->assertOk();

        $this->assertSame(['Crane', 'Excavator', 'Piling Rig'], $this->names($project));
    }

    public function test_other_projects_are_not_affected(): void
    {
        $one = $this->project();
        $two = $this->project();

        $this->add($this->editor(), $one, ['kind' => 'machinery', 'name' => 'Piling Rig'])->assertOk();

        $this->assertContains('Piling Rig', $this->names($one));
        $this->assertNotContains('Piling Rig', $this->names($two));
        $this->assertSame(0, ProjectResourceCategory::where('project_id', $two->id)->count());
    }

    public function test_worker_lists_work_the_same_way(): void
    {
        $project = $this->project();

        $this->add($this->editor(), $project, ['kind' => 'worker', 'name' => 'Grouting Operator'])->assertOk();

        $this->assertSame([...ResourceCategoryService::DEFAULT_WORKERS, 'Grouting Operator'], $this->names($project, 'worker'));
        $this->assertSame('Tradesman', ProjectResourceCategory::where('name', 'Grouting Operator')->value('group'));
    }

    public function test_only_people_who_can_edit_projects_may_add_a_type(): void
    {
        $project = $this->project();
        $payload = ['kind' => 'machinery', 'name' => 'Piling Rig'];

        $this->postJson("/api/projects/{$project->id}/resource-categories/add", $payload)->assertUnauthorized();
        $this->add($this->user(['projects.view']), $project, $payload)->assertForbidden();
        $this->add($this->user([]), $project, $payload)->assertForbidden();

        $this->assertSame(0, ProjectResourceCategory::count());
    }

    public function test_input_is_validated(): void
    {
        $project = $this->project();
        $editor = $this->editor();

        $this->add($editor, $project, ['kind' => 'machinery'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->add($editor, $project, ['kind' => 'machinery', 'name' => '   '])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->add($editor, $project, ['kind' => 'machinery', 'name' => str_repeat('x', 101)])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->add($editor, $project, ['kind' => 'vehicles', 'name' => 'Piling Rig'])->assertUnprocessable()->assertJsonValidationErrors('kind');
        $this->add($editor, $project, ['name' => 'Piling Rig'])->assertUnprocessable()->assertJsonValidationErrors('kind');

        $this->actingAs($editor)->postJson('/api/projects/999999/resource-categories/add', ['kind' => 'machinery', 'name' => 'Piling Rig'])->assertNotFound();

        $this->assertSame(0, ProjectResourceCategory::count());
    }
}
