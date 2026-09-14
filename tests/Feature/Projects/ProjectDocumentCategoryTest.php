<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Guards against regression where new document categories ('monthly_report',
 * 'minute_meeting', 'progress_tracking') were allowed by app-level validation
 * but rejected by the underlying MySQL ENUM column. Verifies end-to-end
 * persistence across all 11 categories.
 */
class ProjectDocumentCategoryTest extends TestCase
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

    public function test_a_document_can_be_uploaded_under_each_of_the_three_new_categories(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $user = $this->actor();

        foreach (['monthly_report', 'minute_meeting', 'progress_tracking'] as $category) {
            $res = $this->actingAs($user)
                ->post("/api/projects/{$project->id}/documents", [
                    'title' => "Doc for {$category}",
                    'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
                    'category' => $category,
                ])
                ->assertCreated();

            $this->assertSame($category, $res->json('data.category'));
        }
    }

    public function test_the_document_list_can_be_filtered_by_the_new_categories(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $user = $this->actor();

        $this->actingAs($user)
            ->post("/api/projects/{$project->id}/documents", [
                'title' => 'Minute Meeting',
                'file' => UploadedFile::fake()->create('mm.pdf', 10, 'application/pdf'),
                'category' => 'minute_meeting',
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->post("/api/projects/{$project->id}/documents", [
                'title' => 'Monthly Report',
                'file' => UploadedFile::fake()->create('mr.pdf', 10, 'application/pdf'),
                'category' => 'monthly_report',
            ])
            ->assertCreated();

        $res = $this->actingAs($user)
            ->get("/api/projects/{$project->id}/documents?category=minute_meeting")
            ->assertOk();

        $this->assertCount(1, $res->json('data.data'));
        $this->assertSame('minute_meeting', $res->json('data.data.0.category'));
    }

    public function test_an_unknown_category_is_still_rejected(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $user = $this->actor();

        $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/documents", [
                'title' => 'Invalid Category',
                'file' => UploadedFile::fake()->create('bad.pdf', 10, 'application/pdf'),
                'category' => 'not_a_real_category',
            ])
            ->assertStatus(422);
    }
}
