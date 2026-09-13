<?php

namespace Tests\Feature\Tasks;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TaskAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['tasks.view', 'tasks.create'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function actor(): User
    {
        $u = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);

        $u->givePermissionTo(['tasks.view', 'tasks.create']);

        return $u;
    }

    public function test_uploading_a_file_attaches_it_to_the_task(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $user = $this->actor();

        $res = $this->actingAs($user)
            ->postJson('/api/tasks', [
                'title' => 'Task with attachment',
                'project_id' => $project->id,
                'attachments' => [
                    UploadedFile::fake()->create('task-file.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertStatus(201);

        $this->assertCount(1, $res->json('data.attachments'));
        $this->assertSame('task-file.pdf', $res->json('data.attachments.0.file_name'));
    }

    public function test_downloading_an_attached_task_file_streams_it_back(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $user = $this->actor();

        $task = $this->actingAs($user)
            ->postJson('/api/tasks', [
                'title' => 'Task with attachment',
                'project_id' => $project->id,
                'attachments' => [
                    UploadedFile::fake()->create('task-file.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertStatus(201)
            ->json('data');

        $this->actingAs($user)
            ->get("/api/tasks/{$task['id']}/attachments/{$task['attachments'][0]['id']}/download")
            ->assertOk();
    }

    public function test_downloading_an_attachment_through_the_wrong_task_is_rejected(): void
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
        $user = $this->actor();

        $taskA = $this->actingAs($user)
            ->postJson('/api/tasks', [
                'title' => 'Task A',
                'project_id' => $project->id,
                'attachments' => [
                    UploadedFile::fake()->create('secret.pdf', 10, 'application/pdf'),
                ],
            ])
            ->assertStatus(201)
            ->json('data');

        $taskB = $this->actingAs($user)
            ->postJson('/api/tasks', [
                'title' => 'Task B',
                'project_id' => $project->id,
            ])
            ->assertStatus(201)
            ->json('data');

        $this->actingAs($user)
            ->get("/api/tasks/{$taskB['id']}/attachments/{$taskA['attachments'][0]['id']}/download")
            ->assertNotFound();
    }
}
