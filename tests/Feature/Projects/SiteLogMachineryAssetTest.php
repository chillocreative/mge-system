<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\SiteLog;
use App\Models\SiteLogMachinery;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleProjectAssignment;
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

    public function test_linking_a_vehicle_in_a_site_log_opens_a_project_assignment(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $asset = Vehicle::create(['registration_no' => 'JCB-01', 'make' => 'JCB', 'model' => '3CX', 'type' => 'machinery', 'status' => 'active']);

        $this->actingAs($this->actor())
            ->postJson("/api/projects/{$project->id}/site-logs", [
                'log_date' => now()->toDateString(),
                'work_performed' => 'Excavation',
                'machinery' => [
                    ['machinery_type' => 'Excavator', 'quantity' => 1, 'vehicle_id' => $asset->id],
                ],
            ])
            ->assertCreated();

        $this->assertTrue(
            VehicleProjectAssignment::where('vehicle_id', $asset->id)
                ->where('project_id', $project->id)
                ->whereNull('released_at')
                ->exists()
        );
    }

    public function test_linking_a_vehicle_already_assigned_elsewhere_closes_the_old_assignment_and_opens_a_new_one(): void
    {
        $oldProject = Project::create(['name' => 'P1', 'code' => 'P1'.uniqid(), 'status' => 'in_progress']);
        $newProject = Project::create(['name' => 'P2', 'code' => 'P2'.uniqid(), 'status' => 'in_progress']);
        $asset = Vehicle::create(['registration_no' => 'JCB-01', 'make' => 'JCB', 'model' => '3CX', 'type' => 'machinery', 'status' => 'active']);

        $oldUser = User::create([
            'first_name' => 'O',
            'last_name' => 'U',
            'email' => 'ou-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);

        VehicleProjectAssignment::create([
            'vehicle_id' => $asset->id,
            'project_id' => $oldProject->id,
            'assigned_at' => now()->subDays(5)->toDateString(),
            'released_at' => null,
            'created_by' => $oldUser->id,
        ]);

        $this->actingAs($this->actor())
            ->postJson("/api/projects/{$newProject->id}/site-logs", [
                'log_date' => now()->toDateString(),
                'work_performed' => 'Drilling',
                'machinery' => [
                    ['machinery_type' => 'Crane', 'quantity' => 1, 'vehicle_id' => $asset->id],
                ],
            ])
            ->assertCreated();

        $oldAssignment = VehicleProjectAssignment::where('vehicle_id', $asset->id)
            ->where('project_id', $oldProject->id)
            ->sole();
        $this->assertNotNull($oldAssignment->released_at);

        $this->assertTrue(
            VehicleProjectAssignment::where('vehicle_id', $asset->id)
                ->where('project_id', $newProject->id)
                ->whereNull('released_at')
                ->exists()
        );
    }

    public function test_linking_a_vehicle_already_assigned_to_the_same_project_creates_no_duplicate_assignment(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $asset = Vehicle::create(['registration_no' => 'JCB-01', 'make' => 'JCB', 'model' => '3CX', 'type' => 'machinery', 'status' => 'active']);

        $existingUser = User::create([
            'first_name' => 'E',
            'last_name' => 'U',
            'email' => 'eu-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);

        VehicleProjectAssignment::create([
            'vehicle_id' => $asset->id,
            'project_id' => $project->id,
            'assigned_at' => now()->subDays(2)->toDateString(),
            'released_at' => null,
            'created_by' => $existingUser->id,
        ]);

        $this->actingAs($this->actor())
            ->postJson("/api/projects/{$project->id}/site-logs", [
                'log_date' => now()->toDateString(),
                'work_performed' => 'Backfilling',
                'machinery' => [
                    ['machinery_type' => 'Dump Truck', 'quantity' => 2, 'vehicle_id' => $asset->id],
                ],
            ])
            ->assertCreated();

        $count = VehicleProjectAssignment::where('vehicle_id', $asset->id)->count();
        $this->assertSame(1, $count);
    }

    public function test_updating_a_site_log_to_link_a_vehicle_also_assigns_it(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $asset = Vehicle::create(['registration_no' => 'JCB-01', 'make' => 'JCB', 'model' => '3CX', 'type' => 'machinery', 'status' => 'active']);

        $log = SiteLog::create([
            'project_id' => $project->id,
            'log_date' => now()->toDateString(),
            'logged_by' => $this->actor()->id,
            'work_performed' => 'Foundation work',
        ]);

        $this->actingAs($this->actor())
            ->putJson("/api/projects/{$project->id}/site-logs/{$log->id}", [
                'log_date' => now()->toDateString(),
                'work_performed' => 'Foundation work',
                'machinery' => [
                    ['machinery_type' => 'Excavator', 'quantity' => 1, 'vehicle_id' => $asset->id],
                ],
            ])
            ->assertOk();

        $this->assertTrue(
            VehicleProjectAssignment::where('vehicle_id', $asset->id)
                ->where('project_id', $project->id)
                ->whereNull('released_at')
                ->exists()
        );
    }

    public function test_a_machinery_row_without_vehicle_id_creates_no_assignment(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);

        $this->actingAs($this->actor())
            ->postJson("/api/projects/{$project->id}/site-logs", [
                'log_date' => now()->toDateString(),
                'work_performed' => 'Survey',
                'machinery' => [
                    ['machinery_type' => 'Generator', 'quantity' => 1],
                ],
            ])
            ->assertCreated();

        $this->assertSame(0, VehicleProjectAssignment::count());
    }
}
