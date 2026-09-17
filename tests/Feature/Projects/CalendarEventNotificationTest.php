<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ciri 4 — notify selected attendees when a project calendar event is created,
 * but only if they are project members. The creator is never notified.
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

    public function test_attendees_who_are_project_members_are_notified(): void
    {
        Notification::fake();
        $actor = $this->actor();
        $attendee = $this->member();
        $project = $this->projectWithMembers([$actor->id, $attendee->id]);

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/events", [
            'title' => 'Safety Inspection',
            'start_datetime' => now()->addDay()->toDateTimeString(),
            'attendees' => [$attendee->id],
        ])->assertCreated();

        Notification::assertSentTo($attendee, \App\Notifications\CalendarEventInviteNotification::class);
    }

    public function test_non_attendee_project_members_are_not_notified(): void
    {
        Notification::fake();
        $actor = $this->actor();
        $attendee = $this->member();
        $nonAttendee = $this->member();
        $project = $this->projectWithMembers([$actor->id, $attendee->id, $nonAttendee->id]);

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/events", [
            'title' => 'Safety Inspection',
            'start_datetime' => now()->addDay()->toDateTimeString(),
            'attendees' => [$attendee->id],
        ])->assertCreated();

        Notification::assertSentTo($attendee, \App\Notifications\CalendarEventInviteNotification::class);
        Notification::assertNotSentTo($nonAttendee, \App\Notifications\CalendarEventInviteNotification::class);
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
            'attendees' => [$actor->id, $other->id],
        ]);

        Notification::assertNotSentTo($actor, \App\Notifications\CalendarEventInviteNotification::class);
        Notification::assertSentTo($other, \App\Notifications\CalendarEventInviteNotification::class);
    }

    public function test_no_attendees_means_no_notification_and_no_error(): void
    {
        Notification::fake();
        $actor = $this->actor();
        $project = $this->projectWithMembers([$actor->id]);

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/events", [
            'title' => 'Solo Planning',
            'start_datetime' => now()->addDay()->toDateTimeString(),
            'attendees' => [],
        ])->assertCreated();

        Notification::assertNothingSent();
    }
}
