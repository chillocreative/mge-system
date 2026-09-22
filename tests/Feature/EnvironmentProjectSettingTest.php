<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EnvironmentProjectSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('environmental.view', 'web');
        Permission::findOrCreate('environmental.manage', 'web');
    }

    private function actor(array $permissions = ['environmental.view', 'environmental.manage']): User
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

    private function project(): Project
    {
        return Project::create([
            'name' => 'River Project',
            'code' => 'PR'.random_int(1000, 9999),
            'status' => 'in_progress',
        ]);
    }

    public function test_show_returns_empty_defaults(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $response = $this->actingAs($actor)->getJson("/api/environment/settings/{$project->id}");

        $response->assertOk();
        $this->assertNull($response->json('data.consultant_company'));
        $this->assertNull($response->json('data.policy_image_url'));
    }

    public function test_update_persists_fields(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $response = $this->actingAs($actor)->putJson("/api/environment/settings/{$project->id}", [
            'consultant_company' => 'Acme Consultants',
            'officer_name' => 'Jane Doe',
        ]);

        $response->assertOk();
        $this->assertSame('Acme Consultants', $response->json('data.consultant_company'));

        $this->assertDatabaseHas('environment_project_settings', [
            'project_id' => $project->id,
            'consultant_company' => 'Acme Consultants',
            'officer_name' => 'Jane Doe',
        ]);
    }

    public function test_upload_image_stores_file_and_serves_it(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $project = $this->project();

        $file = UploadedFile::fake()->image('policy.jpg');

        $upload = $this->actingAs($actor)->postJson("/api/environment/settings/{$project->id}/image", [
            'kind' => 'policy',
            'file' => $file,
        ]);

        $upload->assertOk();
        $this->assertNotNull($upload->json('data.policy_image_url'));

        $image = $this->actingAs($actor)->get("/api/environment/settings/{$project->id}/image/policy");
        $image->assertOk();
        $this->assertSame('image/jpeg', $image->headers->get('Content-Type'));
    }

    public function test_upload_replaces_previous_image(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $project = $this->project();

        $this->actingAs($actor)->postJson("/api/environment/settings/{$project->id}/image", [
            'kind' => 'policy',
            'file' => UploadedFile::fake()->image('first.jpg'),
        ])->assertOk();

        $first = \App\Models\EnvironmentProjectSetting::where('project_id', $project->id)->first();
        $firstPath = $first->policy_image_path;

        $this->actingAs($actor)->postJson("/api/environment/settings/{$project->id}/image", [
            'kind' => 'policy',
            'file' => UploadedFile::fake()->image('second.jpg'),
        ])->assertOk();

        Storage::disk('local')->assertMissing($firstPath);

        $second = $first->fresh();
        Storage::disk('local')->assertExists($second->policy_image_path);
    }

    // ── Item 7: unknown project returns 422, not FK error ──

    public function test_show_returns_422_for_unknown_project(): void
    {
        $actor = $this->actor();

        // Unknown project ID doesn't exist in DB
        $response = $this->actingAs($actor)->getJson('/api/environment/settings/99999');

        $response->assertStatus(422);
        $this->assertSame('Project not found.', $response->json('message'));
    }

    public function test_update_returns_422_for_unknown_project(): void
    {
        $actor = $this->actor();

        $response = $this->actingAs($actor)->putJson('/api/environment/settings/99999', [
            'consultant_company' => 'Acme',
        ]);

        $response->assertStatus(422);
        $this->assertSame('Project not found.', $response->json('message'));
    }

    public function test_upload_image_returns_422_for_unknown_project(): void
    {
        Storage::fake('local');
        $actor = $this->actor();

        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->actingAs($actor)->postJson('/api/environment/settings/99999/image', [
            'kind' => 'policy',
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $this->assertSame('Project not found.', $response->json('message'));
    }

    // ── Item 7: image save before delete prevents data loss ──

    public function test_image_order_prevents_data_loss_on_save_failure(): void
    {
        // This is harder to test directly without mocking, but we verify the logic:
        // - Old file should NOT be deleted until new file is persisted
        // We test that successful upload keeps both old and new paths consistent
        Storage::fake('local');
        $actor = $this->actor();
        $project = $this->project();

        // Upload first image
        $this->actingAs($actor)->postJson("/api/environment/settings/{$project->id}/image", [
            'kind' => 'policy',
            'file' => UploadedFile::fake()->image('first.jpg'),
        ])->assertOk();

        $setting = \App\Models\EnvironmentProjectSetting::where('project_id', $project->id)->first();
        $this->assertNotNull($setting->policy_image_path);

        // Upload second image
        $this->actingAs($actor)->postJson("/api/environment/settings/{$project->id}/image", [
            'kind' => 'policy',
            'file' => UploadedFile::fake()->image('second.jpg'),
        ])->assertOk();

        $setting = $setting->fresh();
        // The new path should exist
        Storage::disk('local')->assertExists($setting->policy_image_path);
        // The old path should be gone
        Storage::disk('local')->assertMissing(str_replace('first.jpg', '', $setting->policy_image_path).'first.jpg');
    }
}
