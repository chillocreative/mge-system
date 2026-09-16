<?php

namespace Tests\Feature\Assets;

use App\Models\Project;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleProjectAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciri 24.x — "Assigned To Project" on the Machinery form syncs
 * VehicleProjectAssignment via AssetService::syncProjectAssignment.
 */
class VehicleProjectSyncTest extends TestCase
{
    use RefreshDatabase;

    private function project(string $code): Project
    {
        return Project::create([
            'name' => 'P'.$code,
            'code' => $code.uniqid(),
            'status' => 'in_progress',
        ]);
    }

    private function actor(): User
    {
        Permission::findOrCreate('assets.manage', 'web');
        $user = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
        ]);
        $user->givePermissionTo('assets.manage');

        return $user;
    }

    /**
     * Create a vehicle via the API endpoint, optionally assigned to a project.
     */
    private function postVehicle(User $actor, array $payload = []): Vehicle
    {
        $registration = $payload['registration_no'] ?? 'V'.uniqid();
        $payload['registration_no'] = $registration;

        $response = $this->actingAs($actor)->postJson('/api/vehicles', array_merge([
            'make' => 'Komatsu',
            'type' => 'machinery',
        ], $payload));

        $response->assertStatus(201);

        return Vehicle::where('registration_no', $registration)->sole();
    }

    public function test_post_with_project_creates_vehicle_and_open_assignment(): void
    {
        $actor = $this->actor();
        $p = $this->project('T1');

        $v = $this->postVehicle($actor, ['project_id' => $p->id]);

        $assignment = $v->fresh()->currentProjectAssignment;
        $this->assertNotNull($assignment);
        $this->assertSame($p->id, $assignment->project_id);
        $this->assertNull($assignment->released_at);
    }

    public function test_put_with_different_project_closes_old_and_opens_new(): void
    {
        $actor = $this->actor();
        $pOld = $this->project('O');
        $pNew = $this->project('N');
        $v = $this->postVehicle($actor, ['project_id' => $pOld->id]);

        $oldAssignment = $v->fresh()->currentProjectAssignment;
        $this->assertNotNull($oldAssignment);

        $response = $this->actingAs($actor)->putJson("/api/vehicles/{$v->id}", [
            'project_id' => $pNew->id,
        ]);
        $response->assertStatus(200);

        $this->assertNotNull($oldAssignment->fresh()->released_at);

        $open = VehicleProjectAssignment::where('vehicle_id', $v->id)->whereNull('released_at')->get();
        $this->assertCount(1, $open);
        $this->assertSame($pNew->id, $open->first()->project_id);
    }

    public function test_put_with_empty_project_id_releases_current_assignment(): void
    {
        $actor = $this->actor();
        $p = $this->project('R');
        $v = $this->postVehicle($actor, ['project_id' => $p->id]);

        $oldAssignment = $v->fresh()->currentProjectAssignment;
        $this->assertNotNull($oldAssignment);

        $response = $this->actingAs($actor)->putJson("/api/vehicles/{$v->id}", [
            'project_id' => '',
        ]);
        $response->assertStatus(200);

        $this->assertNotNull($oldAssignment->fresh()->released_at);

        $openCount = VehicleProjectAssignment::where('vehicle_id', $v->id)->whereNull('released_at')->count();
        $this->assertSame(0, $openCount);

        $totalCount = VehicleProjectAssignment::where('vehicle_id', $v->id)->count();
        $this->assertSame(1, $totalCount);
    }

    public function test_put_without_project_key_keeps_existing_assignment_untouched(): void
    {
        $actor = $this->actor();
        $p = $this->project('U');
        $v = $this->postVehicle($actor, ['project_id' => $p->id]);

        $originalAssignment = $v->fresh()->currentProjectAssignment;
        $this->assertNotNull($originalAssignment);

        $response = $this->actingAs($actor)->putJson("/api/vehicles/{$v->id}", [
            'make' => 'Updated Make Name',
        ]);
        $response->assertStatus(200);

        $updated = $v->fresh()->currentProjectAssignment;
        $this->assertNotNull($updated);
        $this->assertSame($originalAssignment->id, $updated->id);
        $this->assertSame($originalAssignment->project_id, $updated->project_id);
        $this->assertNull($updated->released_at);
    }

    public function test_put_with_same_project_id_does_not_create_duplicate(): void
    {
        $actor = $this->actor();
        $p = $this->project('D');
        $v = $this->postVehicle($actor, ['project_id' => $p->id]);

        $originalCount = VehicleProjectAssignment::where('vehicle_id', $v->id)->count();

        $response = $this->actingAs($actor)->putJson("/api/vehicles/{$v->id}", [
            'project_id' => $p->id,
        ]);
        $response->assertStatus(200);

        $newCount = VehicleProjectAssignment::where('vehicle_id', $v->id)->count();
        $this->assertSame($originalCount, $newCount);

        $openCount = VehicleProjectAssignment::where('vehicle_id', $v->id)->whereNull('released_at')->count();
        $this->assertSame(1, $openCount);
    }
}
