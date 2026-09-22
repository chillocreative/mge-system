<?php

namespace Tests\Feature\Assets;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Assets menu split — "Vehicles" and "Machine" are the same `vehicles` table,
 * split by the new `category` column (vehicle | machine).
 */
class VehicleCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        Permission::findOrCreate('assets.manage', 'web');
        Permission::findOrCreate('assets.view', 'web');
        $user = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
        ]);
        $user->givePermissionTo(['assets.manage', 'assets.view']);

        return $user;
    }

    /**
     * The summary cards on each page must count only that page's category. `total_vehicles`
     * was Vehicle::count() regardless of the filter, so both the Vehicles page and the Machine
     * page reported the whole register — 46 where the answers are 20 and 26.
     */
    public function test_expiring_summary_counts_only_the_requested_category(): void
    {
        $actor = $this->actor();

        foreach (['M-1', 'M-2', 'M-3'] as $reg) {
            Vehicle::create(['registration_no' => $reg, 'make' => 'Volvo', 'type' => 'machinery',
                'status' => 'active', 'category' => 'machine']);
        }
        Vehicle::create(['registration_no' => 'V-1', 'make' => 'Honda', 'type' => 'car',
            'status' => 'active', 'category' => 'vehicle']);

        $all = $this->actingAs($actor)->getJson('/api/assets/expiring');
        $all->assertOk();
        $this->assertSame(4, $all->json('data.total_vehicles'));

        $machines = $this->actingAs($actor)->getJson('/api/assets/expiring?category=machine');
        $machines->assertOk();
        $this->assertSame(3, $machines->json('data.total_vehicles'));
        $this->assertSame(3, $machines->json('data.active_vehicles'));

        $vehicles = $this->actingAs($actor)->getJson('/api/assets/expiring?category=vehicle');
        $vehicles->assertOk();
        $this->assertSame(1, $vehicles->json('data.total_vehicles'));

        // An unknown category is ignored rather than silently returning zero.
        $bogus = $this->actingAs($actor)->getJson('/api/assets/expiring?category=lorry');
        $bogus->assertOk();
        $this->assertSame(4, $bogus->json('data.total_vehicles'));
    }

    public function test_vehicle_created_with_no_explicit_category_defaults_to_vehicle(): void
    {
        $vehicle = Vehicle::create([
            'registration_no' => 'V'.uniqid(),
            'make' => 'Komatsu',
            'type' => 'machinery',
            'status' => 'active',
        ]);

        $this->assertSame('vehicle', $vehicle->fresh()->category);
    }

    public function test_index_filters_by_category(): void
    {
        $actor = $this->actor();

        Vehicle::create(['registration_no' => 'M-'.uniqid(), 'make' => 'Komatsu', 'type' => 'machinery', 'category' => 'machine', 'status' => 'active']);
        Vehicle::create(['registration_no' => 'C-'.uniqid(), 'make' => 'Toyota', 'type' => 'car', 'category' => 'vehicle', 'status' => 'active']);

        $machines = $this->actingAs($actor)->getJson('/api/vehicles?category=machine')->assertStatus(200)->json('data.data');
        $this->assertCount(1, $machines);
        $this->assertSame('machine', $machines[0]['category']);

        $vehicles = $this->actingAs($actor)->getJson('/api/vehicles?category=vehicle')->assertStatus(200)->json('data.data');
        $this->assertCount(1, $vehicles);
        $this->assertSame('vehicle', $vehicles[0]['category']);

        $all = $this->actingAs($actor)->getJson('/api/vehicles')->assertStatus(200)->json('data.data');
        $this->assertCount(2, $all);
    }

    public function test_store_accepts_and_persists_category_machine(): void
    {
        $actor = $this->actor();

        $response = $this->actingAs($actor)->postJson('/api/vehicles', [
            'registration_no' => 'V'.uniqid(),
            'make' => 'Komatsu',
            'type' => 'machinery',
            'category' => 'machine',
        ]);

        $response->assertStatus(201);
        $this->assertSame('machine', $response->json('data.category'));
    }

    public function test_store_rejects_invalid_category(): void
    {
        $actor = $this->actor();

        $response = $this->actingAs($actor)->postJson('/api/vehicles', [
            'registration_no' => 'V'.uniqid(),
            'make' => 'Komatsu',
            'type' => 'machinery',
            'category' => 'lorry',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('category');
    }
}
