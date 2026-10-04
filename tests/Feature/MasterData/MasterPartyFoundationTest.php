<?php

namespace Tests\Feature\MasterData;

use App\Models\Client;
use App\Models\MasterParty;
use App\Models\PartyCategory;
use App\Models\Project;
use App\Models\ProjectReferenceAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MasterPartyFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['master-data.manage', 'projects.view', 'projects.edit'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->actor = User::create([
            'first_name' => 'Master',
            'last_name' => 'Data',
            'email' => 'master-data-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->actor->givePermissionTo(['master-data.manage', 'projects.view', 'projects.edit']);
    }

    public function test_party_creation_stores_two_contacts_and_projects_to_legacy_clients(): void
    {
        $clientCategory = PartyCategory::where('slug', 'client')->firstOrFail();

        $response = $this->actingAs($this->actor)->postJson('/api/master-data/parties', [
            'name' => 'Alpha Builders Sdn Bhd',
            'initial' => 'ABS',
            'category_ids' => [$clientCategory->id],
            'country' => 'Malaysia',
            'is_active' => true,
            'contacts' => [
                'main' => ['name' => 'Amin', 'position' => 'Director', 'phone' => '0111111111', 'email' => 'amin@alpha.test'],
                'additional' => ['name' => 'Bella', 'position' => 'Manager', 'phone' => '0122222222', 'email' => 'bella@alpha.test'],
            ],
        ])->assertCreated();

        $party = MasterParty::with('contacts')->findOrFail($response->json('data.id'));
        $this->assertCount(2, $party->contacts);
        $this->assertSame(['additional', 'main'], $party->contacts->pluck('contact_type')->sort()->values()->all());
        $this->assertDatabaseHas('clients', [
            'master_party_id' => $party->id,
            'company_name' => 'Alpha Builders Sdn Bhd',
            'contact_person' => 'Amin',
            'email' => 'amin@alpha.test',
        ]);
    }

    public function test_lookup_returns_company_names_only_and_safe_delete_rejects_referenced_party(): void
    {
        $party = MasterParty::create([
            'name' => 'Referenced Company',
            'normalized_name' => 'referenced company',
            'initial' => 'RFC',
            'is_active' => true,
        ]);
        Client::create([
            'master_party_id' => $party->id,
            'company_name' => $party->name,
            'contact_person' => 'Person',
            'email' => 'reference-'.uniqid().'@example.com',
            'status' => 'active',
        ]);

        $lookup = $this->actingAs($this->actor)
            ->getJson('/api/master-data/party-options?search=Referenced')
            ->assertOk();
        $this->assertSame(['id', 'name'], array_keys($lookup->json('data.0')));

        $this->actingAs($this->actor)
            ->deleteJson("/api/master-data/parties/{$party->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('party');
        $this->assertNotSoftDeleted($party);
    }

    public function test_reference_templates_preview_and_allocate_approved_formats(): void
    {
        $project = Project::create([
            'name' => 'Olak Project',
            'code' => 'OLAK',
            'status' => 'in_progress',
        ]);

        $expected = [
            'MA' => 'MGE/JPS-TGOLAK/MA/26-001',
            'RFI' => 'MGE/OLAK/RFI/VOL1/1026/001',
            'SITE_MEMO' => 'MGE/TGOLAK/SM/26-001',
        ];
        foreach ($expected as $type => $reference) {
            $this->actingAs($this->actor)
                ->getJson("/api/projects/{$project->id}/document-references/preview?type={$type}&date=2026-10-04")
                ->assertOk()
                ->assertJsonPath('data.reference_no', $reference);
        }

        $this->actingAs($this->actor)
            ->postJson("/api/projects/{$project->id}/document-references/next", ['type' => 'MA', 'date' => '2026-10-04'])
            ->assertCreated()
            ->assertJsonPath('data.reference_no', 'MGE/JPS-TGOLAK/MA/26-001');
        $this->actingAs($this->actor)
            ->postJson("/api/projects/{$project->id}/document-references/next", ['type' => 'MA', 'date' => '2026-10-04'])
            ->assertCreated()
            ->assertJsonPath('data.reference_no', 'MGE/JPS-TGOLAK/MA/26-002');

        $this->assertSame(2, ProjectReferenceAllocation::where('project_id', $project->id)->count());
    }

    public function test_project_codes_are_settings_and_change_the_preview(): void
    {
        $project = Project::create(['name' => 'Configurable', 'code' => 'CFG', 'status' => 'in_progress']);
        $configuration = $this->actingAs($this->actor)
            ->getJson("/api/projects/{$project->id}/reference-settings")
            ->assertOk()
            ->json('data');
        $configuration['settings']['company_code'] = 'ABC';
        $configuration['settings']['client_code'] = 'XYZ';
        $configuration['settings']['primary_project_code'] = 'P01';

        $this->actingAs($this->actor)
            ->putJson("/api/projects/{$project->id}/reference-settings", [
                'settings' => $configuration['settings'],
                'templates' => $configuration['templates'],
            ])->assertOk();

        $this->actingAs($this->actor)
            ->getJson("/api/projects/{$project->id}/document-references/preview?type=MA&date=2026-10-04")
            ->assertOk()
            ->assertJsonPath('data.reference_no', 'ABC/XYZ-P01/MA/26-001');
    }
}
