<?php

namespace App\Services;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Mail;

class MailSettingService
{
    public function get(): ?MailSetting
    {
        return MailSetting::first();
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
