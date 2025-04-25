<?php

namespace Grpaiva\PrismAgents;

use Illuminate\Support\ServiceProvider;

class PrismAgentsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        /*
         * Optional methods to load your package assets
         */
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'prism-agents');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'prism-agents');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            // Publish configuration
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('prism-agents.php'),
            ], 'config');

            // Publish migrations
//            $this->publishes([
//                __DIR__.'/../database/migrations/create_prism_agent_tables.php' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_prism_agent_tables.php'),
//            ], 'migrations');

            // Publish views if you have any
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/prism-agents'),
            ], 'views');

            // Register package commands if you have any
            // $this->commands([]);
        }

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'prism-agents');

        // Load routes if you have any
        if (config('prism-agents.ui.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'prism-agents');

        // Register the facade
        $this->app->singleton('prism-agents', function () {
            return new class {
                // Forward method calls to the PrismAgents class
                public function __call($method, $args)
                {
                    return PrismAgents::$method(...$args);
                }
            };
        });

        // Register any service providers that your package depends on
        // $this->app->register(SomeServiceProvider::class);
    }
}
