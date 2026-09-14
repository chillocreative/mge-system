<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Emergency contact fields on the System Users module (users table) — a
 * separate, additive feature from the identically-named fields on the
 * Staff/Employee HR module (employees table).
 */
class UserEmergencyContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function actor(array $permissions = ['users.create', 'users.edit', 'users.view']): User
    {
        $u = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);
        $u->givePermissionTo($permissions);

        return $u;
    }

    public function test_store_persists_emergency_contact_fields(): void
    {
        $actor = $this->actor();

        $response = $this->actingAs($actor)->postJson('/api/users', [
            'full_name' => 'John Doe',
            'email' => 'john-'.uniqid().'@mge-eng.com',
            'password' => 'password123',
            'role' => 'Employee',
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '012-3456789',
            'emergency_contact_relationship' => 'Spouse',
        ]);

        $response->assertCreated();
        $this->assertSame('Jane Doe', $response->json('data.emergency_contact_name'));
        $this->assertSame('012-3456789', $response->json('data.emergency_contact_phone'));
        $this->assertSame('Spouse', $response->json('data.emergency_contact_relationship'));

        $this->assertDatabaseHas('users', [
            'email' => $response->json('data.email'),
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '012-3456789',
            'emergency_contact_relationship' => 'Spouse',
        ]);
    }

    public function test_update_persists_emergency_contact_fields(): void
    {
        $actor = $this->actor();
        $user = User::create([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'existing-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);

        $response = $this->actingAs($actor)->putJson("/api/users/{$user->id}", [
            'full_name' => 'Existing User',
            'emergency_contact_name' => 'Updated Contact',
            'emergency_contact_phone' => '019-8887777',
            'emergency_contact_relationship' => 'Parent',
        ]);

        $response->assertOk();
        $this->assertSame('Updated Contact', $response->json('data.emergency_contact_name'));
        $this->assertSame('019-8887777', $response->json('data.emergency_contact_phone'));
        $this->assertSame('Parent', $response->json('data.emergency_contact_relationship'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'emergency_contact_name' => 'Updated Contact',
            'emergency_contact_phone' => '019-8887777',
            'emergency_contact_relationship' => 'Parent',
        ]);
    }

    public function test_store_succeeds_without_emergency_contact_fields(): void
    {
        $actor = $this->actor();

        $response = $this->actingAs($actor)->postJson('/api/users', [
            'full_name' => 'No Contact',
            'email' => 'nocontact-'.uniqid().'@mge-eng.com',
            'password' => 'password123',
            'role' => 'Employee',
        ]);

        $response->assertCreated();
        $this->assertNull($response->json('data.emergency_contact_name'));
        $this->assertNull($response->json('data.emergency_contact_phone'));
        $this->assertNull($response->json('data.emergency_contact_relationship'));
    }
}
