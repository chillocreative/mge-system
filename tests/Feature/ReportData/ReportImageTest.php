<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectProgressPeriod;
use App\Models\ReportImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportImageTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->editor = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $this->editor->givePermissionTo(['projects.view', 'projects.edit']);
    }

    public function test_upload_list_filter_and_inline_view(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
        $period = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15']);

        $res = $this->actingAs($this->editor)->post("/api/projects/{$project->id}/report-images", [
            'image' => UploadedFile::fake()->image('aerial1.jpg', 800, 600),
            'section' => 'progress', 'label' => 'Aerial 1', 'period_id' => $period->id, 'taken_on' => '2026-01-15',
        ])->assertCreated();
        $id = $res->json('data.id');
        Storage::disk('local')->assertExists(ReportImage::find($id)->file_path);

        $this->actingAs($this->editor)->post("/api/projects/{$project->id}/report-images", [
            'image' => UploadedFile::fake()->image('map.png'), 'section' => 'location',
        ])->assertCreated();

        $list = $this->actingAs($this->editor)->getJson("/api/projects/{$project->id}/report-images?section=progress")->assertOk();
        $this->assertCount(1, $list->json('data'));
        $this->assertSame('Aerial 1', $list->json('data.0.label'));

        $view = $this->actingAs($this->editor)->get("/api/projects/{$project->id}/report-images/{$id}/view");
        $view->assertOk();
        $this->assertStringContainsString('inline', $view->headers->get('content-disposition'));
    }

    public function test_bad_extension_and_bad_section_are_rejected(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);

        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/report-images", [
            'image' => UploadedFile::fake()->create('x.exe', 10), 'section' => 'location',
        ])->assertStatus(422);
        $this->actingAs($this->editor)->postJson("/api/projects/{$project->id}/report-images", [
            'image' => UploadedFile::fake()->image('x.jpg'), 'section' => 'nope',
        ])->assertStatus(422);
    }
}
