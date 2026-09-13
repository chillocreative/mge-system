<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\SiteLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SiteLogAttachmentTest extends TestCase
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

    public function test_uploading_a_file_attaches_it_to_the_site_log(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $user = $this->actor();
        $log = SiteLog::create(['project_id' => $project->id, 'log_date' => '2026-09-05', 'logged_by' => $user->id]);

        $res = $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/site-logs/{$log->id}/files", [
                'files' => [UploadedFile::fake()->create('site-photo.jpg', 500, 'image/jpeg')],
            ])
            ->assertOk();

        $this->assertCount(1, $res->json('data.attachments'));
        $this->assertSame('site-photo.jpg', $res->json('data.attachments.0.original_name'));
    }

    public function test_downloading_an_attached_site_log_file_streams_it_back(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $user = $this->actor();
        $log = SiteLog::create(['project_id' => $project->id, 'log_date' => '2026-09-05', 'logged_by' => $user->id]);

        $upload = $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/site-logs/{$log->id}/files", [
                'files' => [UploadedFile::fake()->create('site-photo.jpg', 500, 'image/jpeg')],
            ]);
        $attachmentId = $upload->json('data.attachments.0.id');

        $this->actingAs($user)
            ->get("/api/projects/{$project->id}/site-logs/{$log->id}/attachments/{$attachmentId}/download")
            ->assertOk();
    }

    public function test_the_site_log_index_and_show_include_attachments(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $user = $this->actor();
        $log = SiteLog::create(['project_id' => $project->id, 'log_date' => '2026-09-05', 'logged_by' => $user->id]);

        $this->actingAs($user)->postJson("/api/projects/{$project->id}/site-logs/{$log->id}/files", [
            'files' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        ]);

        $show = $this->actingAs($user)->getJson("/api/projects/{$project->id}/site-logs/{$log->id}")->assertOk();
        $this->assertCount(1, $show->json('data.attachments'));

        $index = $this->actingAs($user)->getJson("/api/projects/{$project->id}/site-logs")->assertOk();
        $this->assertCount(1, $index->json('data.data.0.attachments'));
    }

    public function test_downloading_an_attachment_through_the_wrong_site_log_is_rejected(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $user = $this->actor();
        $ownerLog = SiteLog::create(['project_id' => $project->id, 'log_date' => '2026-09-05', 'logged_by' => $user->id]);
        $otherLog = SiteLog::create(['project_id' => $project->id, 'log_date' => '2026-09-06', 'logged_by' => $user->id]);

        $upload = $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/site-logs/{$ownerLog->id}/files", [
                'files' => [UploadedFile::fake()->create('secret.pdf', 10, 'application/pdf')],
            ]);
        $attachmentId = $upload->json('data.attachments.0.id');

        $this->actingAs($user)
            ->get("/api/projects/{$project->id}/site-logs/{$otherLog->id}/attachments/{$attachmentId}/download")
            ->assertNotFound();
    }
}
