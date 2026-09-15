<?php

namespace Tests\Feature\Settings;

use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MailSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['settings.view', 'settings.manage'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function user(array $perms): User
    {
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo($perms);

        return $u;
    }

    public function test_show_returns_null_when_no_settings_saved_yet(): void
    {
        $this->actingAs($this->user(['settings.view']))
            ->getJson('/api/settings/mail')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_a_viewer_without_manage_permission_cannot_update(): void
    {
        $this->actingAs($this->user(['settings.view']))
            ->putJson('/api/settings/mail', [
                'host' => 'mail.mge-eng.com', 'port' => 587, 'username' => 'noreply@mge-eng.com',
                'password' => 'secret', 'encryption' => 'tls',
                'from_address' => 'noreply@mge-eng.com', 'from_name' => 'MGE-PMS',
            ])
            ->assertStatus(403);
    }

    public function test_update_saves_settings_and_never_returns_the_password(): void
    {
        $res = $this->actingAs($this->user(['settings.view', 'settings.manage']))
            ->putJson('/api/settings/mail', [
                'host' => 'mail.mge-eng.com', 'port' => 587, 'username' => 'noreply@mge-eng.com',
                'password' => 'secret', 'encryption' => 'tls',
                'from_address' => 'noreply@mge-eng.com', 'from_name' => 'MGE-PMS', 'enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.host', 'mail.mge-eng.com')
            ->assertJsonPath('data.has_password', true)
            ->assertJsonPath('data.enabled', true);

        $this->assertArrayNotHasKey('password', $res->json('data'));

        $setting = MailSetting::first();
        $this->assertSame('secret', $setting->password);
    }

    public function test_updating_without_a_password_keeps_the_existing_one(): void
    {
        MailSetting::create([
            'host' => 'mail.mge-eng.com', 'port' => 587, 'username' => 'noreply@mge-eng.com',
            'password' => 'original-secret', 'encryption' => 'tls',
            'from_address' => 'noreply@mge-eng.com', 'from_name' => 'MGE-PMS', 'enabled' => true,
        ]);

        $this->actingAs($this->user(['settings.view', 'settings.manage']))
            ->putJson('/api/settings/mail', [
                'host' => 'mail.mge-eng.com', 'port' => 587, 'username' => 'noreply@mge-eng.com',
                'encryption' => 'tls', 'from_address' => 'noreply@mge-eng.com', 'from_name' => 'MGE Updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.from_name', 'MGE Updated')
            ->assertJsonPath('data.has_password', true);

        $this->assertSame('original-secret', MailSetting::first()->password);
    }

    public function test_test_endpoint_sends_without_error_using_the_resolved_settings(): void
    {
        Mail::fake();

        MailSetting::create([
            'host' => 'mail.mge-eng.com', 'port' => 587, 'username' => 'noreply@mge-eng.com',
            'password' => 'secret', 'encryption' => 'tls',
            'from_address' => 'noreply@mge-eng.com', 'from_name' => 'MGE-PMS', 'enabled' => true,
        ]);

        $this->actingAs($this->user(['settings.view', 'settings.manage']))
            ->postJson('/api/settings/mail/test', ['to' => 'check@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Test email sent to check@example.com.');
    }

    public function test_test_endpoint_accepts_ad_hoc_overrides_before_saving(): void
    {
        Mail::fake();

        $this->actingAs($this->user(['settings.view', 'settings.manage']))
            ->postJson('/api/settings/mail/test', [
                'to' => 'check@example.com', 'host' => 'mail.mge-eng.com', 'port' => 587,
                'username' => 'noreply@mge-eng.com', 'password' => 'secret', 'encryption' => 'tls',
                'from_address' => 'noreply@mge-eng.com', 'from_name' => 'MGE-PMS',
            ])
            ->assertOk();

        $this->assertNull(MailSetting::first());
    }
}
