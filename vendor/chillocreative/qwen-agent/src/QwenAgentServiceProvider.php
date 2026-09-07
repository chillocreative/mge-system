<?php

namespace Chillocreative\QwenAgent;

use Chillocreative\QwenAgent\Console\Commands\QwenAgent;
use Illuminate\Support\ServiceProvider;

class QwenAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/qwen.php', 'qwen');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                QwenAgent::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/qwen.php' => config_path('qwen.php'),
            ], 'qwen-agent-config');
        }
    }
}
