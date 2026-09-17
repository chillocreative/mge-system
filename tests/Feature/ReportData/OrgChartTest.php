<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrgChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_chart_is_saved_and_returned_as_a_tree(): void
    {
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $editor->givePermissionTo(['projects.view', 'projects.edit']);
        $pm = User::create(['first_name' => 'Norazlinda', 'last_name' => 'Sabarudin', 'email' => 'pm-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $se = User::create(['first_name' => 'Ahmad', 'last_name' => 'Haziq', 'email' => 'se-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
        $project->members()->attach([$pm->id => ['role' => 'member'], $se->id => ['role' => 'member']]);

        $this->actingAs($editor)->putJson("/api/projects/{$project->id}/org-chart", ['members' => [
            ['user_id' => $pm->id, 'designation' => 'Project Manager', 'reports_to_user_id' => null, 'org_sort' => 0],
            ['user_id' => $se->id, 'designation' => 'Senior Engineer', 'reports_to_user_id' => $pm->id, 'org_sort' => 0],
        ]])->assertOk();

        $res = $this->actingAs($editor)->getJson("/api/projects/{$project->id}/org-chart")->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Project Manager', $res->json('data.0.designation'));
        $this->assertSame('Senior Engineer', $res->json('data.0.children.0.designation'));
    }

    public function test_update_rejects_a_reporting_line_cycle_and_stores_nothing(): void
    {
        [$editor, $project, $a, $b] = $this->makeProjectWithTwoMembers();

        $this->actingAs($editor)->putJson("/api/projects/{$project->id}/org-chart", ['members' => [
            ['user_id' => $a->id, 'designation' => 'A', 'reports_to_user_id' => $b->id, 'org_sort' => 0],
            ['user_id' => $b->id, 'designation' => 'B', 'reports_to_user_id' => $a->id, 'org_sort' => 0],
        ]])->assertStatus(422);

        $res = $this->actingAs($editor)->getJson("/api/projects/{$project->id}/org-chart")->assertOk();
        // Nothing was persisted: both rows still have no designation and no manager, so both are roots.
        $this->assertCount(2, $res->json('data'));
        $this->assertNull($res->json('data.0.designation'));
        $this->assertNull($res->json('data.1.designation'));
    }

    public function test_a_member_whose_manager_left_the_project_still_appears_at_top_level(): void
    {
        [$editor, $project, $a, $b] = $this->makeProjectWithTwoMembers();

        $this->actingAs($editor)->putJson("/api/projects/{$project->id}/org-chart", ['members' => [
            ['user_id' => $a->id, 'designation' => 'Manager', 'reports_to_user_id' => null, 'org_sort' => 0],
            ['user_id' => $b->id, 'designation' => 'Report', 'reports_to_user_id' => $a->id, 'org_sort' => 0],
        ]])->assertOk();

        $project->members()->updateExistingPivot($a->id, ['left_at' => now()]);

        $res = $this->actingAs($editor)->getJson("/api/projects/{$project->id}/org-chart")->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Report', $res->json('data.0.designation'));
        $this->assertSame([], $res->json('data.0.children'));
    }

    public function test_update_rejects_reports_to_a_member_who_has_left(): void
    {
        [$editor, $project, $a, $b] = $this->makeProjectWithTwoMembers();
        $project->members()->updateExistingPivot($a->id, ['left_at' => now()]);

        $this->actingAs($editor)->putJson("/api/projects/{$project->id}/org-chart", ['members' => [
            ['user_id' => $b->id, 'designation' => 'Report', 'reports_to_user_id' => $a->id, 'org_sort' => 0],
        ]])->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: Project, 2: User, 3: User}
     */
    private function makeProjectWithTwoMembers(): array
    {
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $editor->givePermissionTo(['projects.view', 'projects.edit']);
        $a = User::create(['first_name' => 'Norazlinda', 'last_name' => 'Sabarudin', 'email' => 'pm-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $b = User::create(['first_name' => 'Ahmad', 'last_name' => 'Haziq', 'email' => 'se-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
        $project->members()->attach([$a->id => ['role' => 'member'], $b->id => ['role' => 'member']]);

        return [$editor, $project, $a, $b];
    }
}
