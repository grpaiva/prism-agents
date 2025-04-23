<?php

namespace Grpaiva\PrismAgents;

use Illuminate\Support\ServiceProvider;

class PrismAgentsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        /*
         * Optional methods to load your package assets
         */
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'prism-agents');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'prism-agents');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('prism-agents.php'),
            ], 'config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/prism-agents'),
            ], 'views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/prism-agents'),
            ], 'assets');*/

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/prism-agents'),
            ], 'lang');*/

            // Registering package commands.
            // $this->commands([]);

            // Publish migration for traces
            $this->publishes([
                __DIR__.'/../database/migrations/create_prism_agent_traces_table.php' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_prism_agent_traces_table.php'),
            ], 'migrations');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'prism-agents');

        // Since PrismAgents now uses static methods, we only need to register the facade
        $this->app->singleton('prism-agents', function () {
            return new class {
                // Forward method calls to the PrismAgents class
                public function __call($method, $args)
                {
                    return PrismAgents::$method(...$args);
                }
            };
        });
    }
}
