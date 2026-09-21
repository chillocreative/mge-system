<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SiteForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SiteFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('projects.view', 'web');
        Permission::findOrCreate('projects.edit', 'web');
    }

    private function actor(array $permissions = ['projects.view', 'projects.edit']): User
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
            'name' => 'P',
            'code' => 'PR'.random_int(1000, 9999),
            'status' => 'in_progress',
        ]);
    }

    public function test_store_and_index_filtered_by_form_type_and_search(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $response = $this->actingAs($actor)->postJson('/api/site-forms', [
            'form_type' => 'rfwi',
            'project_id' => $project->id,
            'ref_no' => 'MGE/PR1/RFWI/26-01',
            'title' => 'Foundation inspection',
        ]);
        $response->assertCreated();

        $this->actingAs($actor)->postJson('/api/site-forms', [
            'form_type' => 'ncr',
            'project_id' => $project->id,
            'ref_no' => 'MGE/PR1/NCR/26-01',
            'title' => 'Rebar spacing issue',
        ])->assertCreated();

        $index = $this->actingAs($actor)->getJson('/api/site-forms?form_type=rfwi');
        $index->assertOk();
        $data = $index->json('data.data');
        $this->assertCount(1, $data);
        $this->assertSame('rfwi', $data[0]['form_type']);

        $search = $this->actingAs($actor)->getJson('/api/site-forms?form_type=ncr&search=Rebar');
        $search->assertOk();
        $this->assertCount(1, $search->json('data.data'));
    }

    public function test_update_changes_data_json_and_title(): void
    {
        $actor = $this->actor();
        $form = SiteForm::create([
            'form_type' => 'ncr',
            'ref_no' => 'MGE/MGE/NCR/26-01',
            'title' => 'Old title',
        ]);

        $response = $this->actingAs($actor)->putJson("/api/site-forms/{$form->id}", [
            'title' => 'New title',
            'data' => ['description' => 'Updated'],
        ]);

        $response->assertOk();
        $form->refresh();
        $this->assertSame('New title', $form->title);
        $this->assertSame('Updated', $form->data['description']);
    }

    public function test_destroy_soft_deletes(): void
    {
        $actor = $this->actor();
        $form = SiteForm::create([
            'form_type' => 'ncr',
            'ref_no' => 'MGE/MGE/NCR/26-01',
        ]);

        $this->actingAs($actor)->deleteJson("/api/site-forms/{$form->id}")->assertOk();

        $this->assertSoftDeleted('site_forms', ['id' => $form->id]);
    }

    public function test_invalid_form_type_returns_422(): void
    {
        $actor = $this->actor();

        $response = $this->actingAs($actor)->postJson('/api/site-forms', [
            'form_type' => 'not_a_type',
            'ref_no' => 'X',
        ]);

        $response->assertStatus(422);
    }

    public function test_view_only_user_gets_403_on_store(): void
    {
        $actor = $this->actor(['projects.view']);

        $response = $this->actingAs($actor)->postJson('/api/site-forms', [
            'form_type' => 'ncr',
            'ref_no' => 'MGE/MGE/NCR/26-01',
        ]);

        $response->assertStatus(403);
    }

    public function test_next_ref_increments_sequence(): void
    {
        $actor = $this->actor();
        $project = $this->project();

        $year = now()->format('y');

        $first = $this->actingAs($actor)->getJson("/api/site-forms/next-ref?form_type=rfwi&project_id={$project->id}");
        $first->assertOk();
        $this->assertSame("MGE/{$project->code}/RFWI/{$year}-01", $first->json('data.ref_no'));

        SiteForm::create([
            'form_type' => 'rfwi',
            'project_id' => $project->id,
            'ref_no' => $first->json('data.ref_no'),
        ]);

        $second = $this->actingAs($actor)->getJson("/api/site-forms/next-ref?form_type=rfwi&project_id={$project->id}");
        $second->assertOk();
        $this->assertSame("MGE/{$project->code}/RFWI/{$year}-02", $second->json('data.ref_no'));
    }

    public function test_attachment_upload_and_slot_replacement(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $form = SiteForm::create([
            'form_type' => 'ncr',
            'ref_no' => 'MGE/MGE/NCR/26-01',
        ]);

        $file1 = UploadedFile::fake()->create('a.pdf', 10);
        $this->actingAs($actor)->postJson("/api/site-forms/{$form->id}/attachments", [
            'slot' => 'photo',
            'file' => $file1,
        ])->assertCreated();

        $this->assertCount(1, $form->attachments()->get());

        $file2 = UploadedFile::fake()->create('b.pdf', 10);
        $this->actingAs($actor)->postJson("/api/site-forms/{$form->id}/attachments", [
            'slot' => 'photo',
            'file' => $file2,
        ])->assertCreated();

        $this->assertCount(1, $form->attachments()->get());
    }
}
