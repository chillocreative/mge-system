<?php

namespace App\Providers;

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
    }
}
