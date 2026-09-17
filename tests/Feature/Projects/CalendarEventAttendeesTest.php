<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\User;
use App\Notifications\CalendarEventInviteNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Attendees on a project calendar event receive an in-app notification and an
 * email invite (CalendarEventInviteNotification) on create, and on update for
 * newly-added attendees only. API responses also expose attendee_users.
 */
class CalendarEventAttendeesTest extends TestCase
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

    private function member(string $first = 'M', string $last = 'X'): User
    {
        return User::create([
            'first_name' => $first,
            'last_name' => $last,
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

    public function test_creating_an_event_with_attendees_notifies_them_and_not_the_creator(): void
    {
        Notification::fake();
        $actor = $this->actor();
        $attendee1 = $this->member();
        $attendee2 = $this->member();
        $project = $this->projectWithMembers([$actor->id, $attendee1->id, $attendee2->id]);

        $this->actingAs($actor)->postJson("/api/projects/{$project->id}/events", [
            'title' => 'Safety Inspection',
            'start_datetime' => now()->addDay()->toDateTimeString(),
            'attendees' => [$attendee1->id, $attendee2->id],
        ])->assertCreated();

        Notification::assertSentTo([$attendee1, $attendee2], CalendarEventInviteNotification::class);
        Notification::assertNotSentTo($actor, CalendarEventInviteNotification::class);
    }

    public function test_the_invite_notification_is_transactional_with_mail_channel(): void
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

        $notification = Notification::sent($attendee, CalendarEventInviteNotification::class)->first();

        $this->assertNotNull($notification);
        $this->assertContains('mail', $notification->via($attendee));
        $this->assertStringStartsWith('Invitation:', $notification->toMail($attendee)->subject);
    }

    public function test_updating_an_event_to_add_an_attendee_notifies_only_the_new_one(): void
    {
        Notification::fake();
        $actor = $this->actor();
        $attendeeA = $this->member();
        $attendeeB = $this->member();
        $project = $this->projectWithMembers([$actor->id, $attendeeA->id, $attendeeB->id]);

        $response = $this->actingAs($actor)->postJson("/api/projects/{$project->id}/events", [
            'title' => 'Initial Meeting',
            'start_datetime' => now()->addDay()->toDateTimeString(),
            'attendees' => [$attendeeA->id],
        ])->assertCreated();

        $eventId = $response->json('data.id');

        // Isolate the update's sends from creation's.
        Notification::fake();

        $this->actingAs($actor)->putJson("/api/projects/{$project->id}/events/{$eventId}", [
            'title' => 'Updated Meeting',
            'attendees' => [$attendeeA->id, $attendeeB->id],
        ])->assertOk();

        Notification::assertSentTo($attendeeB, CalendarEventInviteNotification::class);
        Notification::assertNotSentTo($attendeeA, CalendarEventInviteNotification::class);
    }

    public function test_responses_include_attendee_users_with_names(): void
    {
        Notification::fake();
        $actor = $this->actor();
        $attendee = $this->member('Jane', 'Doe');
        $project = $this->projectWithMembers([$actor->id, $attendee->id]);

        $response = $this->actingAs($actor)->postJson("/api/projects/{$project->id}/events", [
            'title' => 'Project Kickoff',
            'start_datetime' => now()->addDay()->toDateTimeString(),
            'attendees' => [$attendee->id],
        ])->assertCreated();

        $attendeeUsers = $response->json('data.attendee_users');
        $this->assertCount(1, $attendeeUsers);
        $this->assertSame('Jane', $attendeeUsers[0]['first_name']);
        $this->assertSame('Doe', $attendeeUsers[0]['last_name']);

        $showResponse = $this->actingAs($actor)
            ->getJson("/api/projects/{$project->id}/events/{$response->json('data.id')}")
            ->assertOk();

        $showAttendeeUsers = $showResponse->json('data.attendee_users');
        $this->assertCount(1, $showAttendeeUsers);
        $this->assertSame('Jane', $showAttendeeUsers[0]['first_name']);
        $this->assertSame('Doe', $showAttendeeUsers[0]['last_name']);
    }
}
