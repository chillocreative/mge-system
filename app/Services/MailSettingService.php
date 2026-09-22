<?php

namespace App\Services;

use App\Models\MailSetting;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Mail;

class MailSettingService
{
    public function get(): ?MailSetting
    {
        return MailSetting::first();
    }

    /**
     * Delivery diagnostics for Settings > Email: whether SMTP is actually wired
     * up to send notification emails (not just whether the test button works),
     * plus a recent log so an admin can see what has been sent/skipped/failed.
     */
    public function status(): array
    {
        $setting = MailSetting::first();
        $smtpEnabled = (bool) $setting?->enabled;
        $effectiveMailer = (string) config('mail.default');
        $emailEnabled = (bool) config('notifications.email_enabled');

        $since = now()->subDays(7);
        $counts = NotificationLog::query()
            ->where('channel', 'mail')
            ->where('created_at', '>=', $since)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $last7Days = [
            'sent' => (int) ($counts['sent'] ?? 0),
            'skipped' => (int) ($counts['skipped'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
        ];

        $recent = NotificationLog::query()
            ->with('user:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (NotificationLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at,
                'type' => $log->type,
                'channel' => $log->channel,
                'title' => $log->title,
                'status' => $log->status,
                'error' => $log->error,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => trim("{$log->user->first_name} {$log->user->last_name}"),
                ] : null,
            ])
            ->all();

        $problems = $this->deliveryWarnings($setting, $smtpEnabled, $effectiveMailer, $emailEnabled);
        if ($last7Days['failed'] > 0) {
            $problems[] = "{$last7Days['failed']} email(s) failed in the last 7 days — see the log below.";
        }

        return [
            'smtp_configured' => (bool) ($setting && filled($setting->host) && filled($setting->username)),
            'smtp_enabled' => $smtpEnabled,
            'effective_mailer' => $effectiveMailer,
            'notifications_email_enabled' => $emailEnabled,
            'email_max_per_hour' => (int) config('notifications.email_max_per_hour'),
            'last_7_days' => $last7Days,
            'recent' => $recent,
            'problems' => $problems,
        ];
    }

    /**
     * Hints shared between the status endpoint and the test-email response, so a
     * green "test sent" toast never misleads an admin into thinking notification
     * emails are actually going out.
     *
     * @return array<int, string>
     */
    public function deliveryWarnings(?MailSetting $setting = null, ?bool $smtpEnabled = null, ?string $effectiveMailer = null, ?bool $emailEnabled = null): array
    {
        $setting ??= MailSetting::first();
        $smtpEnabled ??= (bool) $setting?->enabled;
        $effectiveMailer ??= (string) config('mail.default');
        $emailEnabled ??= (bool) config('notifications.email_enabled');

        $warnings = [];

        if (! $setting) {
            $warnings[] = 'No SMTP settings saved yet.';
        } elseif (! $smtpEnabled) {
            $warnings[] = "SMTP settings are saved but not enabled — notification emails are currently sent to the '{$effectiveMailer}' mailer, not to recipients. Tick 'Enable SMTP' and save.";
        }

        if (! $emailEnabled) {
            $warnings[] = 'Email notifications are disabled by NOTIFICATIONS_EMAIL_ENABLED=false on the server.';
        }

        return $warnings;
    }

    public function save(array $data): MailSetting
    {
        $setting = MailSetting::first();

        // Password is optional on update — blank/missing means "keep the existing one".
        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($setting) {
            $setting->update($data);

            return $setting->fresh();
        }

        return MailSetting::create($data);
    }

    /**
     * Send a test email using either the values passed in $overrides (for testing before
     * saving) or, for any key not present in $overrides, the currently saved setting.
     * Applies the resolved config for this request only — never persists it.
     */
    public function sendTest(string $to, array $overrides = []): void
    {
        $setting = MailSetting::first();

        $resolve = fn (string $key, $default = null) => array_key_exists($key, $overrides) && $overrides[$key] !== ''
            ? $overrides[$key]
            : ($setting?->{$key} ?? $default);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $resolve('host'),
            'mail.mailers.smtp.port' => $resolve('port'),
            'mail.mailers.smtp.username' => $resolve('username'),
            'mail.mailers.smtp.password' => $resolve('password'),
            'mail.mailers.smtp.encryption' => $resolve('encryption') ?: null,
            'mail.from.address' => $resolve('from_address'),
            'mail.from.name' => $resolve('from_name'),
        ]);

        Mail::mailer('smtp')->raw(
            'This is a test email from MGE-PMS to confirm your SMTP settings are working correctly.',
            function ($message) use ($to) {
                $message->to($to)->subject('MGE-PMS — SMTP Test Email');
            }
        );
    }
}
