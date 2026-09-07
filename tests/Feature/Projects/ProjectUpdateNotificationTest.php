<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Ciri 3 — notify project members when a project meaningfully changes, without
 * becoming noise (plan 3.3: field blacklist + exclude the actor).
 */
class ProjectUpdateNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProjectService
    {
        return app(ProjectService::class);
    }

    private function member(): User
    {
        return User::create(['first_name' => 'M', 'last_name' => 'X', 'email' => 'm-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
    }

    private function projectWithMembers(array $userIds): Project
    {
        $project = Project::create(['name' => 'Bridge', 'code' => 'BRG'.uniqid(), 'status' => 'in_progress', 'budget' => 1000]);
        $project->members()->attach(collect($userIds)->mapWithKeys(fn ($id) => [$id => ['role' => 'member', 'joined_at' => now()]]));

        return $project;
    }

    public function test_members_are_notified_of_a_meaningful_change(): void
    {
        Notification::fake();
        $m1 = $this->member();
        $project = $this->projectWithMembers([$m1->id]);

        $this->service()->updateProject($project->id, ['status' => 'on_hold']);

        Notification::assertSentTo($m1, \App\Notifications\SystemNotification::class);
        $this->assertDatabaseHas('notification_logs', ['user_id' => $m1->id, 'type' => 'project']);
    }

    public function test_the_actor_who_made_the_change_is_not_notified(): void
    {
        Notification::fake();
        $actor = $this->member();
        $other = $this->member();
        $project = $this->projectWithMembers([$actor->id, $other->id]);

        $this->actingAs($actor);
        $this->service()->updateProject($project->id, ['status' => 'completed']);

        Notification::assertNotSentTo($actor, \App\Notifications\SystemNotification::class);
        Notification::assertSentTo($other, \App\Notifications\SystemNotification::class);
    }

    public function test_no_notification_when_nothing_meaningful_changed(): void
    {
        Notification::fake();
        $m1 = $this->member();
        $project = $this->projectWithMembers([$m1->id]);

        // Re-save the same status — no real change.
        $this->service()->updateProject($project->id, ['status' => 'in_progress']);

        Notification::assertNothingSent();
    }

    public function test_a_blacklisted_field_alone_does_not_notify(): void
    {
        Notification::fake();
        $m1 = $this->member();
        $project = $this->projectWithMembers([$m1->id]);

        // 'notes' is not in the meaningful set (if the column exists it is ignored).
        $this->service()->updateProject($project->id, ['updated_at' => now()->addDay()]);

        Notification::assertNothingSent();
    }
}
