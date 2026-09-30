<?php

namespace Tests\Feature\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskAssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name): User
    {
        return User::create([
            'first_name' => $name,
            'last_name' => 'User',
            'email' => strtolower($name).'-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    private function project(): Project
    {
        return Project::create([
            'name' => 'Bridge Project',
            'code' => 'BRG'.uniqid(),
            'status' => 'in_progress',
        ]);
    }

    public function test_every_assignee_is_notified_when_a_task_is_created(): void
    {
        Notification::fake();
        $creator = $this->user('Creator');
        $first = $this->user('First');
        $second = $this->user('Second');

        $this->actingAs($creator);
        $task = app(TaskService::class)->createTask([
            'title' => 'Inspect reinforcement',
            'project_id' => $this->project()->id,
        ], [$first->id, $second->id]);

        Notification::assertSentTo($first, SystemNotification::class, function (SystemNotification $notification) use ($first, $task) {
            $data = $notification->toArray($first);

            return $data['link'] === '/tasks'
                && $data['extra']['task_id'] === $task->id
                && $data['title'] === 'New task assigned';
        });
        Notification::assertSentTo($second, SystemNotification::class);
        $this->assertDatabaseHas('notification_logs', ['user_id' => $first->id, 'type' => 'task', 'channel' => 'database']);
        $this->assertDatabaseHas('notification_logs', ['user_id' => $second->id, 'type' => 'task', 'channel' => 'database']);
    }

    public function test_only_newly_added_assignees_are_notified_when_assignments_change(): void
    {
        Notification::fake();
        $creator = $this->user('Creator');
        $existing = $this->user('Existing');
        $new = $this->user('New');
        $removed = $this->user('Removed');
        $project = $this->project();

        $this->actingAs($creator);
        $task = Task::create([
            'title' => 'Review method statement',
            'project_id' => $project->id,
            'created_by' => $creator->id,
            'assigned_to' => $existing->id,
        ]);
        $task->assignees()->sync([$existing->id, $removed->id]);

        app(TaskService::class)->updateTask($task->id, [], [$existing->id, $new->id]);

        Notification::assertSentToTimes($new, SystemNotification::class, 1);
        Notification::assertNotSentTo($existing, SystemNotification::class);
        Notification::assertNotSentTo($removed, SystemNotification::class);
    }

    public function test_unchanged_assignments_do_not_send_notifications(): void
    {
        Notification::fake();
        $creator = $this->user('Creator');
        $assignee = $this->user('Assignee');

        $this->actingAs($creator);
        $task = Task::create([
            'title' => 'Check site access',
            'project_id' => $this->project()->id,
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
        ]);
        $task->assignees()->sync([$assignee->id]);

        app(TaskService::class)->updateTask($task->id, [], [$assignee->id]);

        Notification::assertNothingSent();
    }
}
