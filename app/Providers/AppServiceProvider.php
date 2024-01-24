<?php

namespace App\Providers;

use App\Services\BlackholeManager;
use App\Services\DownloadManager;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        //
        $this->app->singleton("BlackholeManager", function ($app) {
            return new BlackholeManager();
        });

        $this->app->singleton("DownloadManager", function ($app) {
            return new DownloadManager();
        });

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LogViewer::auth(function ($request) {
            return $request->user();
        });

    }
}
