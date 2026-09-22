<?php

namespace Tests\Feature;

use App\Models\MailSetting;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Covers GET /api/settings/mail/status — the delivery diagnostics endpoint added
 * so an admin can tell whether notification emails are actually being handed to
 * SMTP, not just whether the "send test" button worked.
 */
class MailSettingStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('settings.view', 'web');
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
        $u->givePermissionTo('settings.view');

        return $u;
    }

    public function test_status_flags_saved_but_disabled_smtp(): void
    {
        $user = $this->actor();

        MailSetting::create([
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'noreply@example.com',
            'password' => 'secret',
            'encryption' => 'tls',
            'from_address' => 'noreply@example.com',
            'from_name' => 'MGE-PMS',
            'enabled' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/settings/mail/status');

        $response->assertOk();
        $response->assertJsonPath('data.smtp_enabled', false);
        $response->assertJsonPath('data.smtp_configured', true);

        $problems = $response->json('data.problems');
        $this->assertNotEmpty($problems);
        $this->assertTrue(
            collect($problems)->contains(fn ($p) => str_contains($p, 'not enabled')),
            'Expected a "not enabled" hint in problems: '.json_encode($problems)
        );
    }

    public function test_status_reports_no_smtp_problems_when_enabled_and_email_on(): void
    {
        config(['notifications.email_enabled' => true]);

        $user = $this->actor();

        MailSetting::create([
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'noreply@example.com',
            'password' => 'secret',
            'encryption' => 'tls',
            'from_address' => 'noreply@example.com',
            'from_name' => 'MGE-PMS',
            'enabled' => true,
        ]);

        // Applying config('mail.default' => 'smtp') mirrors what AppServiceProvider::boot()
        // does when a MailSetting row is enabled — that boot logic already ran before this
        // test's config() override above, so replicate the effect it would have had.
        config(['mail.default' => 'smtp']);

        $response = $this->actingAs($user)->getJson('/api/settings/mail/status');

        $response->assertOk();
        $response->assertJsonPath('data.smtp_enabled', true);
        $response->assertJsonPath('data.notifications_email_enabled', true);

        $problems = collect($response->json('data.problems'));
        $this->assertFalse($problems->contains(fn ($p) => str_contains($p, 'not enabled')));
        $this->assertFalse($problems->contains(fn ($p) => str_contains($p, 'disabled by NOTIFICATIONS_EMAIL_ENABLED')));
    }

    public function test_status_includes_recent_notification_logs(): void
    {
        $user = $this->actor();

        NotificationLog::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'channel' => 'mail',
            'title' => 'Leave request approved',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($user)->getJson('/api/settings/mail/status');

        $response->assertOk();
        $recent = $response->json('data.recent');
        $this->assertNotEmpty($recent);
        $this->assertSame('Leave request approved', $recent[0]['title']);
        $this->assertSame($user->id, $recent[0]['user']['id']);
    }
}
