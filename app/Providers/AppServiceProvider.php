<?php

namespace App\Providers;

use App\Models\Milestone;
use App\Observers\MilestoneObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RepositoryServiceProvider::class);
    }

    public function boot(): void
    {
        // Apply DB-stored SMTP settings over the .env mail config, when present and enabled.
        // Wrapped in try/catch: this runs on every request boot, including before migrations
        // have run on a fresh install, so a missing table must never break the app.
        try {
            $mailSetting = \App\Models\MailSetting::first();
            if ($mailSetting && $mailSetting->enabled) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $mailSetting->host,
                    'mail.mailers.smtp.port' => $mailSetting->port,
                    'mail.mailers.smtp.username' => $mailSetting->username,
                    'mail.mailers.smtp.password' => $mailSetting->password,
                    'mail.mailers.smtp.encryption' => $mailSetting->encryption,
                    'mail.from.address' => $mailSetting->from_address,
                    'mail.from.name' => $mailSetting->from_name,
                ]);
            }
        } catch (\Throwable) {
            // Table doesn't exist yet (fresh install/migrating) — fall back to .env mail config.
        }

        // Point the "forgot password" email's reset link at the SPA route
        // (this API-only backend has no Blade 'password.reset' view/route).
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return config('app.frontend_url').'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });

        // Super-admin bypass: "Admin & HR" role passes every permission
        // check automatically — no need to assign future permissions manually.
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('Admin & HR')) {
                return true;
            }
        });

        // Keep a project's overall completion in sync with the average
        // progress of its milestones, regardless of which entry point
        // creates/updates/deletes a milestone.
        Milestone::observe(MilestoneObserver::class);
    }
}
