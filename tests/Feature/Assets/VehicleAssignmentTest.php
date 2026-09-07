<?php

namespace Tests\Feature\Assets;

use App\Models\Project;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleProjectAssignment;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ciri 24 — a vehicle assigned to a project, with non-overlapping history.
 */
class VehicleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AssetService
    {
        return app(AssetService::class);
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::create(['registration_no' => 'V'.uniqid(), 'make' => 'Komatsu', 'type' => 'machinery', 'status' => 'active']);
    }

    private function project(string $code): Project
    {
        return Project::create(['name' => 'P'.$code, 'code' => $code.uniqid(), 'status' => 'in_progress']);
    }

    private function actor(): User
    {
        return User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x')]);
    }

    public function test_assigning_a_vehicle_records_an_open_assignment(): void
    {
        $v = $this->vehicle();
        $p = $this->project('A');

        $this->service()->assignToProject($v->id, ['project_id' => $p->id, 'assigned_at' => '2026-09-01'], $this->actor()->id);

        $a = VehicleProjectAssignment::where('vehicle_id', $v->id)->sole();
        $this->assertSame($p->id, $a->project_id);
        $this->assertNull($a->released_at);
    }

    public function test_reassigning_closes_the_previous_open_assignment(): void
    {
        $v = $this->vehicle();
        $p1 = $this->project('A');
        $p2 = $this->project('B');
        $actor = $this->actor()->id;

        $this->service()->assignToProject($v->id, ['project_id' => $p1->id, 'assigned_at' => '2026-09-01'], $actor);
        $this->service()->assignToProject($v->id, ['project_id' => $p2->id, 'assigned_at' => '2026-09-15'], $actor);

        // Exactly one open assignment (the newest), history preserved, no overlap.
        $open = VehicleProjectAssignment::where('vehicle_id', $v->id)->whereNull('released_at')->get();
        $this->assertCount(1, $open);
        $this->assertSame($p2->id, $open->first()->project_id);

        $first = VehicleProjectAssignment::where('vehicle_id', $v->id)->where('project_id', $p1->id)->sole();
        $this->assertSame('2026-09-15', $first->released_at->format('Y-m-d'));
    }

    public function test_releasing_sets_the_release_date(): void
    {
        $v = $this->vehicle();
        $p = $this->project('A');
        $actor = $this->actor()->id;
        $this->service()->assignToProject($v->id, ['project_id' => $p->id], $actor);
        $a = VehicleProjectAssignment::where('vehicle_id', $v->id)->sole();

        $this->service()->releaseFromProject($v->id, $a->id);

        $this->assertNotNull($a->fresh()->released_at);
    }

    public function test_current_assignment_relation_returns_only_the_open_one(): void
    {
        $v = $this->vehicle();
        $p1 = $this->project('A');
        $p2 = $this->project('B');
        $actor = $this->actor()->id;
        $this->service()->assignToProject($v->id, ['project_id' => $p1->id], $actor);
        $this->service()->assignToProject($v->id, ['project_id' => $p2->id], $actor);

        $this->assertSame($p2->id, $v->fresh()->currentProjectAssignment->project_id);
    }

    public function test_serial_numbers_are_stored(): void
    {
        $v = $this->service()->createVehicle([
            'registration_no' => 'ABC1234', 'make' => 'Volvo', 'type' => 'lorry',
            'chassis_no' => 'CHS-999', 'engine_no' => 'ENG-888', 'serial_no' => 'SER-777',
        ], $this->actor()->id);

        $this->assertSame('CHS-999', $v->chassis_no);
        $this->assertSame('ENG-888', $v->engine_no);
        $this->assertSame('SER-777', $v->serial_no);
    }
}
