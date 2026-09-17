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
}
