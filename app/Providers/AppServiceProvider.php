<?php

namespace App\Providers;

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
        // Super-admin bypass: "Admin & HR" role passes every permission
        // check automatically — no need to assign future permissions manually.
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('Admin & HR')) {
                return true;
            }
        });
    }
}
