<?php

namespace Grpaiva\PrismAgents\Tests;

use Grpaiva\PrismAgents\PrismAgentsServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use Mockery as m;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\OpenAI\OpenAI;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            PrismAgentsServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $openAI = new OpenAI(
            'test_key',
            'https://api.openai.com/v1',
            null,
            null
        );

        // Intercept any requests to the OpenAI API
        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-mock-001',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4o',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'This is a mocked response from the AI.'
                        ],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 50,
                    'completion_tokens' => 20,
                    'total_tokens' => 70
                ]
            ], 200)
        ]);


        // Create a mock PrismManager that returns the mocked provider
        $prismManager = m::mock(PrismManager::class);
        $prismManager->shouldReceive('provider')->andReturn($openAI);
        $prismManager->shouldReceive('resolve')->withAnyArgs()->andReturn($openAI);

        // Bind the mocked manager to the container
        $this->app->instance(PrismManager::class, $prismManager);
    }

    protected function getEnvironmentSetUp($app): void
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
        
        // Set up Prism config
        $app['config']->set('prism', [
            'default' => 'openai',
            'providers' => [
                'openai' => [
                    'api_key' => 'test_key',
                    'url' => 'https://api.openai.com/v1',
                    'organization' => null,
                    'project' => null,
                ]
            ]
        ]);
    }
} 