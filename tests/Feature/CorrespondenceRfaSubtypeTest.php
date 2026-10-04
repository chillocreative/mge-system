<?php

namespace Tests\Feature;

use App\Models\CorrespondenceRfaSubtype;
use App\Models\CorrespondenceType;
use App\Models\Project;
use App\Models\ProjectCorrespondence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CorrespondenceRfaSubtypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['projects.view', 'projects.edit'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    private function actor(array $permissions): User
    {
        $user = User::create([
            'first_name' => 'RFA',
            'last_name' => 'Manager',
            'email' => 'rfa-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('secret'),
            'status' => 'active',
        ]);
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    public function test_index_returns_the_preserved_default_subtypes_in_order(): void
    {
        $response = $this->actingAs($this->actor(['projects.view']))
            ->getJson('/api/correspondence-rfa-subtypes');

        $response->assertOk()
            ->assertJsonPath('data.0.code', 'MA')
            ->assertJsonPath('data.0.name', 'Material Approval')
            ->assertJsonPath('data.4.code', 'DWG');

        $this->assertSame(
            ['MA', 'MS', 'REPORT', 'TEST', 'DWG'],
            $response->collect('data')->pluck('code')->all(),
        );
    }

    public function test_editor_can_create_a_persistent_subtype_with_a_normalized_code(): void
    {
        $response = $this->actingAs($this->actor(['projects.view', 'projects.edit']))
            ->postJson('/api/correspondence-rfa-subtypes', [
                'code' => '  calcs  ',
                'name' => 'Design Calculations',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'CALCS')
            ->assertJsonPath('data.name', 'Design Calculations');

        $this->assertDatabaseHas('correspondence_rfa_subtypes', [
            'code' => 'CALCS',
            'name' => 'Design Calculations',
        ]);
    }

    public function test_duplicate_subtype_code_is_rejected(): void
    {
        $this->actingAs($this->actor(['projects.view', 'projects.edit']))
            ->postJson('/api/correspondence-rfa-subtypes', [
                'code' => 'ma',
                'name' => 'Duplicate Material Approval',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_unreferenced_subtype_can_be_deleted(): void
    {
        $subtype = CorrespondenceRfaSubtype::create([
            'code' => 'TEMP',
            'name' => 'Temporary',
            'sort_order' => 99,
        ]);

        $this->actingAs($this->actor(['projects.view', 'projects.edit']))
            ->deleteJson("/api/correspondence-rfa-subtypes/{$subtype->id}")
            ->assertOk();

        $this->assertDatabaseMissing('correspondence_rfa_subtypes', ['id' => $subtype->id]);
    }

    public function test_referenced_subtype_cannot_be_deleted_and_correspondence_is_preserved(): void
    {
        $project = Project::create([
            'name' => 'RFA Test Project',
            'code' => 'RFA'.random_int(1000, 9999),
            'status' => 'in_progress',
        ]);
        CorrespondenceType::firstOrCreate(
            ['code' => 'rfa'],
            ['name' => 'RFA', 'color' => 'blue', 'sort_order' => 1, 'is_active' => true],
        );
        $correspondence = ProjectCorrespondence::create([
            'project_id' => $project->id,
            'type' => 'rfa',
            'document_subtype' => 'MA',
            'title' => 'Existing material approval',
            'status' => 'open',
            'raised_date' => now()->toDateString(),
        ]);
        $subtype = CorrespondenceRfaSubtype::where('code', 'MA')->firstOrFail();

        $this->actingAs($this->actor(['projects.view', 'projects.edit']))
            ->deleteJson("/api/correspondence-rfa-subtypes/{$subtype->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subtype');

        $this->assertDatabaseHas('correspondence_rfa_subtypes', ['id' => $subtype->id]);
        $this->assertDatabaseHas('project_correspondences', ['id' => $correspondence->id, 'document_subtype' => 'MA']);
    }

    public function test_permissions_protect_read_and_mutation_endpoints(): void
    {
        $viewer = $this->actor(['projects.view']);
        $subtype = CorrespondenceRfaSubtype::where('code', 'DWG')->firstOrFail();

        $this->actingAs($this->actor([]))
            ->getJson('/api/correspondence-rfa-subtypes')
            ->assertForbidden();
        $this->actingAs($viewer)
            ->postJson('/api/correspondence-rfa-subtypes', ['code' => 'NEW', 'name' => 'New'])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->deleteJson("/api/correspondence-rfa-subtypes/{$subtype->id}")
            ->assertForbidden();
    }
}
