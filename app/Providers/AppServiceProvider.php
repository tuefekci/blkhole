<?php

namespace App\Providers;

use App\Services\BlackholeManager;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope
        //if ($this->app->environment('local')) {
            //$this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            //$this->app->register(TelescopeServiceProvider::class);
        //}

        //
        $this->app->singleton("BlackholeManager", function ($app) {
            return new BlackholeManager();
        });

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Lang::handleMissingKeysUsing(function (string $key, array $replacements, string $locale) {
            //info("Missing translation key [$key] detected.");
     
            // TODO: Add handling for missing translation keys!
            return $key;
        });

    }
}
