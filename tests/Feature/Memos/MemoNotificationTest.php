<?php

namespace Tests\Feature\Memos;

use App\Models\User;
use App\Services\MemoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Ciri 11 — sending a memo notifies its recipients through the shared engine,
 * under the 'memo' preference category (so a recipient can mute or, once email
 * is enabled, opt memos into email), and every send is logged.
 */
class MemoNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create(['first_name' => 'U', 'last_name' => 'X', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
    }

    public function test_recipients_are_notified_under_the_memo_category(): void
    {
        Notification::fake();
        $sender = $this->user();
        $r1 = $this->user();
        $r2 = $this->user();

        app(MemoService::class)->send([
            'title' => 'Office closed Friday',
            'body' => 'The office will be closed this Friday.',
            'audience' => 'selected_users',
            'user_ids' => [$r1->id, $r2->id],
        ], $sender);

        Notification::assertSentTo($r1, \App\Notifications\SystemNotification::class);
        Notification::assertSentTo($r2, \App\Notifications\SystemNotification::class);
        // Sender is never a recipient of their own memo.
        Notification::assertNotSentTo($sender, \App\Notifications\SystemNotification::class);

        $this->assertDatabaseHas('notification_logs', ['user_id' => $r1->id, 'type' => 'memo', 'channel' => 'database']);
    }

    public function test_a_recipient_who_muted_memos_is_not_notified(): void
    {
        Notification::fake();
        $sender = $this->user();
        $muted = $this->user();
        \App\Models\NotificationPreference::create(['user_id' => $muted->id, 'type' => 'memo', 'mode' => 'off']);

        app(MemoService::class)->send([
            'title' => 'FYI',
            'body' => 'Body',
            'audience' => 'selected_users',
            'user_ids' => [$muted->id],
        ], $sender);

        Notification::assertNotSentTo($muted, \App\Notifications\SystemNotification::class);
    }
}
