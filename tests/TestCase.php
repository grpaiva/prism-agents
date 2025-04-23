<?php

namespace Grpaiva\PrismAgents\Tests;

use Grpaiva\PrismAgents\PrismAgentsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            PrismAgentsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        
        // Set up your package configs here
        $app['config']->set('prism-agents.tracing.enabled', true);
        $app['config']->set('prism-agents.tracing.table', 'prism_agent_traces');
    }
} 