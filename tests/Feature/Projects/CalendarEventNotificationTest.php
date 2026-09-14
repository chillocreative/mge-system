<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciri 4 — notify project team members when a calendar event is created,
 * excluding the creator and handling empty recipient lists gracefully.
 */
class CalendarEventNotificationTest extends TestCase
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
        $u = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);
        $u->givePermissionTo(['projects.view', 'projects.edit']);

        return $u;
    }

    private function member(): User
    {
        return User::create([
            'first_name' => 'M',
            'last_name' => 'X',
            'email' => 'm-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);
    }

    private function projectWithMembers(array $userIds): Project
    {
        $project = Project::create([
            'name' => 'Bridge',
            'code' => 'BRG'.uniqid(),
            'status' => 'in_progress',
            'budget' => 1000,
        ]);
        $project->members()->attach(
            collect($userIds)->mapWithKeys(fn ($id) => [$id => ['role' => 'member', 'joined_at' => now()]])
        );

        return $project;
    }

    public function test_project_members_are_notified_when_an_event_is_created(): void
    {
        Notification::fake();
        $actor = $this->actor();
        $member = $this->member();
        $project = $this->projectWithMembers([$actor->id, $member->id]);

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/events", [
            'title' => 'Safety Inspection',
            'start_datetime' => now()->addDay()->toDateTimeString(),
        ])->assertCreated();

        Notification::assertSentTo($member, \App\Notifications\SystemNotification::class);
        $this->assertDatabaseHas('notification_logs', ['user_id' => $member->id, 'type' => 'calendar']);
    }

    public function test_the_event_creator_is_not_notified_of_their_own_event(): void
    {
        Notification::fake();
        $actor = $this->actor();
        $other = $this->member();
        $project = $this->projectWithMembers([$actor->id, $other->id]);

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/events", [
            'title' => 'Team Standup',
            'start_datetime' => now()->addDay()->toDateTimeString(),
        ]);

        Notification::assertNotSentTo($actor, \App\Notifications\SystemNotification::class);
        Notification::assertSentTo($other, \App\Notifications\SystemNotification::class);
    }

    public function test_no_recipients_means_no_notification_and_no_error(): void
    {
        Notification::fake();
        $actor = $this->actor();
        $project = $this->projectWithMembers([$actor->id]);

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/events", [
            'title' => 'Solo Planning',
            'start_datetime' => now()->addDay()->toDateTimeString(),
        ])->assertCreated();

        Notification::assertNothingSent();
    }
}
