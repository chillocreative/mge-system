<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\SiteLogMachinery;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciri 21 — site-log machinery can reference an Asset (a machinery-type
 * vehicle), while free-text entry still works for anything not yet registered.
 */
class SiteLogMachineryAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function actor(): User
    {
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo(['projects.view', 'projects.edit']);

        return $u;
    }

    public function test_a_site_log_machinery_row_can_link_to_an_asset(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $asset = Vehicle::create(['registration_no' => 'JCB-01', 'make' => 'JCB', 'model' => '3CX', 'type' => 'machinery', 'status' => 'active']);

        $res = $this->actingAs($this->actor())
            ->postJson("/api/projects/{$project->id}/site-logs", [
                'log_date' => '2026-09-05',
                'work_performed' => 'Excavation',
                'machinery' => [
                    ['machinery_type' => 'Excavator', 'quantity' => 1, 'vehicle_id' => $asset->id],
                ],
            ])
            ->assertCreated();

        $row = SiteLogMachinery::sole();
        $this->assertSame($asset->id, $row->vehicle_id);
        $this->assertTrue($row->vehicle->is($asset));
    }

    public function test_free_text_machinery_still_works_without_an_asset(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);

        $this->actingAs($this->actor())
            ->postJson("/api/projects/{$project->id}/site-logs", [
                'log_date' => '2026-09-06',
                'work_performed' => 'Compacting',
                'machinery' => [['machinery_type' => 'Compactor', 'quantity' => 2]],
            ])
            ->assertCreated();

        $row = SiteLogMachinery::sole();
        $this->assertNull($row->vehicle_id);
        $this->assertSame('Compactor', $row->machinery_type);
    }

    public function test_an_unknown_asset_id_is_rejected(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);

        $this->actingAs($this->actor())
            ->postJson("/api/projects/{$project->id}/site-logs", [
                'log_date' => '2026-09-06',
                'work_performed' => 'x',
                'machinery' => [['machinery_type' => 'Crane', 'quantity' => 1, 'vehicle_id' => 99999]],
            ])
            ->assertStatus(422);
    }
}
