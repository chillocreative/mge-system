<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_search_matches_full_name_case_insensitively_and_keeps_status_and_pagination(): void
    {
        $actor = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $actor->givePermissionTo('users.view');

        $target = User::create([
            'first_name' => 'Norazlinda',
            'last_name' => 'Binti Sabarudin',
            'email' => 'azlinda@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        User::create([
            'first_name' => 'Norazlinda',
            'last_name' => 'Binti Sabarudin',
            'email' => 'inactive-azlinda@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($actor)->getJson('/api/users?search=nORAzlinda%20binti%20sABARudin&status=active&per_page=1&page=1');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.data.0.id', $target->id)
            ->assertJsonPath('data.data.0.email', 'azlinda@mge-eng.com');
    }

    public function test_search_matches_email_case_insensitively(): void
    {
        $actor = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $actor->givePermissionTo('users.view');

        $target = User::create([
            'first_name' => 'Different',
            'last_name' => 'Name',
            'email' => 'azlinda@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->actingAs($actor)
            ->getJson('/api/users?search=AZLINDA%40EXAMPLE.COM')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $target->id);
    }
}
