<?php

namespace Tests\Feature\Projects;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Same bug class as the staff "Server Error": milestones.status/progress/
 * sort_order are NOT NULL with a DB default, but validated 'nullable'. A blank
 * submitted value becomes null (ConvertEmptyStringsToNull) and used to 500 on
 * the NOT NULL constraint. The controller now drops those null keys so the DB
 * default applies.
 */
class MilestoneNullDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        Permission::findOrCreate('projects.edit', 'web');
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'm-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo('projects.edit');

        return $u;
    }

    public function test_creating_a_milestone_with_null_status_and_progress_succeeds(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'PM'.random_int(1000, 9999), 'status' => 'in_progress']);

        $this->actingAs($this->actor())
            ->postJson('/api/milestones', [
                'project_id' => $project->id,
                'title' => 'Kick-off',
                'status' => null,   // blank in the form → null
                'progress' => null,
            ])
            ->assertCreated();

        $m = Milestone::where('title', 'Kick-off')->firstOrFail();
        $this->assertSame('pending', $m->status);   // DB default
        $this->assertSame(0, (int) $m->progress);   // DB default
    }
}
