<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectCutoffDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_cutoff_day_defaults_to_15_and_can_be_updated(): void
    {
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo(['projects.view', 'projects.edit']);
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);

        $this->assertSame(15, $project->fresh()->report_cutoff_day);

        $this->actingAs($u)->putJson("/api/projects/{$project->id}", ['report_cutoff_day' => 25])->assertOk();
        $this->assertSame(25, $project->fresh()->report_cutoff_day);

        $this->actingAs($u)->putJson("/api/projects/{$project->id}", ['report_cutoff_day' => 31])->assertStatus(422);
    }
}
