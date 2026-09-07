<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Shared notification engine (plan §26.2).
 *
 * The default path must stay exactly what it was before this engine existed:
 * an in-app notification, no email. Email is opt-in AND gated on a global switch
 * that is off in production until SMTP is verified — so these tests pin that the
 * default never emails, and that every send is recorded.
 */
class NotificationEngineTest extends TestCase
{
    use RefreshDatabase;

    private function service(): NotificationService
    {
        return app(NotificationService::class);
    }

    private function user(): User
    {
        return User::create([
            'first_name' => 'A', 'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active',
        ]);
    }

    public function test_default_send_is_in_app_only_and_is_logged(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->service()->notify($user, 'Hello', 'A message', category: 'leave');

        Notification::assertSentTo($user, \App\Notifications\SystemNotification::class, function ($n) use ($user) {
            return $n->via($user) === ['database'];
        });
        $this->assertDatabaseHas('notification_logs', ['user_id' => $user->id, 'channel' => 'database', 'status' => 'sent', 'type' => 'leave']);
    }

    public function test_a_category_set_to_off_sends_nothing(): void
    {
        Notification::fake();
        $user = $this->user();
        NotificationPreference::create(['user_id' => $user->id, 'type' => 'leave', 'mode' => 'off']);

        $this->service()->notify($user, 'Hello', 'msg', category: 'leave');

        Notification::assertNothingSent();
        $this->assertDatabaseHas('notification_logs', ['user_id' => $user->id, 'status' => 'skipped']);
    }

    public function test_email_is_not_added_when_the_global_switch_is_off(): void
    {
        Notification::fake();
        config(['notifications.email_enabled' => false]);
        $user = $this->user();
        NotificationPreference::create(['user_id' => $user->id, 'type' => 'leave', 'mode' => 'instant', 'email_enabled' => true]);

        $this->service()->notify($user, 'Hello', 'msg', category: 'leave');

        // Even though the user opted into email, the master switch wins.
        Notification::assertSentTo($user, \App\Notifications\SystemNotification::class, fn ($n) => $n->via($user) === ['database']);
    }

    public function test_email_is_added_when_switch_on_and_user_opted_in(): void
    {
        Notification::fake();
        config(['notifications.email_enabled' => true]);
        $user = $this->user();
        NotificationPreference::create(['user_id' => $user->id, 'type' => 'leave', 'mode' => 'instant', 'email_enabled' => true]);

        $this->service()->notify($user, 'Hello', 'msg', category: 'leave');

        Notification::assertSentTo($user, \App\Notifications\SystemNotification::class, function ($n) use ($user) {
            return in_array('mail', $n->via($user), true) && in_array('database', $n->via($user), true);
        });
        $this->assertDatabaseHas('notification_logs', ['user_id' => $user->id, 'channel' => 'mail', 'status' => 'sent']);
    }

    public function test_opted_in_but_digest_mode_does_not_email(): void
    {
        Notification::fake();
        config(['notifications.email_enabled' => true]);
        $user = $this->user();
        NotificationPreference::create(['user_id' => $user->id, 'type' => 'leave', 'mode' => 'digest', 'email_enabled' => true]);

        $this->service()->notify($user, 'Hello', 'msg', category: 'leave');

        // Digest delivers in-app now; email batching is a later phase.
        Notification::assertSentTo($user, \App\Notifications\SystemNotification::class, fn ($n) => $n->via($user) === ['database']);
    }

    public function test_email_is_capped_per_hour(): void
    {
        Notification::fake();
        config(['notifications.email_enabled' => true, 'notifications.email_max_per_hour' => 2]);
        $user = $this->user();
        NotificationPreference::create(['user_id' => $user->id, 'type' => 'leave', 'mode' => 'instant', 'email_enabled' => true]);

        for ($i = 0; $i < 4; $i++) {
            $this->service()->notify($user, "Msg {$i}", 'body', category: 'leave');
        }

        // 2 mail sends, then mail capped (in-app still goes out each time).
        $this->assertSame(2, NotificationLog::where('channel', 'mail')->where('status', 'sent')->count());
        $this->assertSame(4, NotificationLog::where('channel', 'database')->where('status', 'sent')->count());
        $this->assertTrue(NotificationLog::where('channel', 'mail')->where('status', 'skipped')->exists());
    }

    public function test_a_null_user_is_a_safe_no_op(): void
    {
        Notification::fake();
        $this->service()->notify(null, 'x', 'y');
        Notification::assertNothingSent();
        $this->assertSame(0, NotificationLog::count());
    }
}
