<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciri — Client selection on project contracts.
 */
class ContractClientTest extends TestCase
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

    private function client(string $name = 'Acme Sdn Bhd'): Client
    {
        return Client::create([
            'company_name' => $name,
            'contact_person' => 'Contact Person',
            'email' => 'client-'.uniqid().'@example.com',
            'status' => 'active',
        ]);
    }

    private function project(?int $clientId = null): Project
    {
        return Project::create([
            'name' => 'P',
            'code' => 'P'.uniqid(),
            'status' => 'in_progress',
            'client_id' => $clientId,
        ]);
    }

    public function test_storing_a_contract_with_client_id_persists_it_and_show_returns_it(): void
    {
        $client = $this->client();
        $project = $this->project();

        $res = $this->actingAs($this->actor())
            ->postJson('/api/project-contracts', [
                'project_id' => $project->id,
                'client_id' => $client->id,
                'title' => 'Main Contract',
                'status' => 'active',
            ])
            ->assertCreated();

        $contractId = $res->json('data.id');
        $this->assertSame($client->id, ProjectContract::find($contractId)->client_id);

        $show = $this->actingAs($this->actor())
            ->getJson("/api/project-contracts/{$contractId}")
            ->assertOk();

        $this->assertSame($client->company_name, $show->json('data.client.company_name'));
    }

    public function test_storing_without_client_id_defaults_to_the_projects_client(): void
    {
        $client = $this->client('Default Client');
        $project = $this->project($client->id);

        $res = $this->actingAs($this->actor())
            ->postJson('/api/project-contracts', [
                'project_id' => $project->id,
                'title' => 'No Explicit Client',
                'status' => 'active',
            ])
            ->assertCreated();

        $contractId = $res->json('data.id');
        $this->assertSame($client->id, ProjectContract::find($contractId)->client_id);
    }

    public function test_an_invalid_client_id_is_rejected(): void
    {
        $project = $this->project();

        $this->actingAs($this->actor())
            ->postJson('/api/project-contracts', [
                'project_id' => $project->id,
                'client_id' => 999999,
                'title' => 'Bad Client',
                'status' => 'active',
            ])
            ->assertStatus(422);
    }
}
