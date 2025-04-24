<?php

use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\PrismAgents;
use Prism\Prism\Enums\Provider;
use Grpaiva\PrismAgents\AgentResult;
use Grpaiva\PrismAgents\Tests\TestHelpers\MocksTrait;
use Grpaiva\PrismAgents\Tests\TestHelpers\PrismMockTrait;
use Mockery as m;

uses(MocksTrait::class, PrismMockTrait::class);

test('agent handoff structure is properly created', function () {
    // Create a specialized agent
    $weatherAgent = Agent::as('weather_agent')
        ->withInstructions('You are a weather specialist. Provide detailed weather information.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withHandoffDescription('A specialist in weather information');

    // Create a general agent that uses the weather agent as a tool
    $generalAgent = Agent::as('general_assistant')
        ->withInstructions('You are a helpful assistant.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withTools([$weatherAgent->asTool()]);

    // Test that the general agent has the weather agent as a tool
    expect($generalAgent->getTools())->toHaveCount(1)
        ->and($generalAgent->getTools()[0]->name())->toBe('weather_agent')
        ->and($generalAgent->getTools()[0]->description())->toBe('A specialist in weather information');
});

test('multi-agent orchestration structure is properly created', function () {
    // Create specialized translation agents
    $spanishAgent = Agent::as('spanish_agent')
        ->withInstructions('You translate the user\'s message to Spanish')
        ->using(Provider::OpenAI, 'gpt-4.1-nano')
        ->withHandoffDescription('An english to spanish translator');

    $frenchAgent = Agent::as('french_agent')
        ->withInstructions('You translate the user\'s message to French')
        ->using(Provider::OpenAI, 'gpt-4.1-nano')
        ->withHandoffDescription('An english to french translator');

    // Create an orchestrator agent
    $orchestratorAgent = Agent::as('orchestrator_agent')
        ->withInstructions("You are a translation agent. Use the tools given to you to translate.")
        ->using(Provider::OpenAI, 'gpt-4.1')
        ->withTools([
            $spanishAgent->asTool(),
            $frenchAgent->asTool()
        ]);

    // Test that the orchestrator agent has both translation agents as tools
    expect($orchestratorAgent->getTools())->toHaveCount(2)
        ->and($orchestratorAgent->getTools()[0]->name())->toBe('spanish_agent')
        ->and($orchestratorAgent->getTools()[1]->name())->toBe('french_agent');
});

test('agent can use another agent as a tool', function () {
    // Create a specialized agent
    $weatherAgent = Agent::as('weather_agent')
        ->withInstructions('You are a weather specialist. Provide detailed weather information.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withHandoffDescription('A specialist in weather information');

    // Create a general agent that uses the weather agent as a tool
    $generalAgent = Agent::as('general_assistant')
        ->withInstructions('You are a helpful assistant.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withTools([$weatherAgent->asTool()]);

    // Mock Prism to simulate tool usage with agent handoff
    app()->instance('Prism\Prism\Prism', m::mock('Prism\Prism\Prism')
        ->shouldReceive('run')
        ->andReturn([
            'output' => 'The weather in New York is partly cloudy with a high of 75°F.',
            'model' => 'gpt-4o',
            'usage' => [
                'input_tokens' => 120,
                'output_tokens' => 60,
                'total_tokens' => 180
            ],
            'tool_calls' => [
                [
                    'tool' => 'weather_agent',
                    'args' => ['input' => 'What is the weather forecast for New York?'],
                    'result' => 'The weather in New York is partly cloudy with a high of 75°F.'
                ]
            ]
        ])
        ->getMock());

    $result = (PrismAgents::run($generalAgent, "What's the weather forecast for New York?"))->get();

    expect($result)->toBeInstanceOf(AgentResult::class)
        ->and($result->getOutput())->toBe('The weather in New York is partly cloudy with a high of 75°F.')
        ->and($result->getToolResults())->toHaveCount(1)
        ->and($result->getToolResults()[0]['toolName'])->toBe('weather_agent');
});

test('multiple agents can be orchestrated', function () {
    // Create specialized translation agents
    $spanishAgent = Agent::as('spanish_agent')
        ->withInstructions('You translate the user\'s message to Spanish')
        ->using(Provider::OpenAI, 'gpt-4.1-nano')
        ->withHandoffDescription('An english to spanish translator');

    $frenchAgent = Agent::as('french_agent')
        ->withInstructions('You translate the user\'s message to French')
        ->using(Provider::OpenAI, 'gpt-4.1-nano')
        ->withHandoffDescription('An english to french translator');

    // Create an orchestrator agent
    $orchestratorAgent = Agent::as('orchestrator_agent')
        ->withInstructions("You are a translation agent. Use the tools given to you to translate.")
        ->using(Provider::OpenAI, 'gpt-4.1')
        ->withTools([
            $spanishAgent->asTool(),
            $frenchAgent->asTool()
        ]);

    // Mock Prism to simulate multiple tool calls
    app()->instance('Prism\Prism\Prism', m::mock('Prism\Prism\Prism')
        ->shouldReceive('run')
        ->andReturn([
            'output' => "Spanish: Hola, ¿cómo estás?\nFrench: Bonjour, comment ça va?",
            'model' => 'gpt-4.1',
            'usage' => [
                'input_tokens' => 150,
                'output_tokens' => 80,
                'total_tokens' => 230
            ],
            'tool_calls' => [
                [
                    'tool' => 'spanish_agent',
                    'args' => ['input' => 'Translate "Hello, how are you?" to Spanish'],
                    'result' => 'Hola, ¿cómo estás?'
                ],
                [
                    'tool' => 'french_agent',
                    'args' => ['input' => 'Translate "Hello, how are you?" to French'],
                    'result' => 'Bonjour, comment ça va?'
                ]
            ]
        ])
        ->getMock());

    $result = (PrismAgents::run($orchestratorAgent, "Translate 'Hello, how are you?' to Spanish and French."))->get();

    expect($result)->toBeInstanceOf(AgentResult::class)
        ->and($result->getOutput())->toContain('Spanish: Hola, ¿cómo estás?')
        ->and($result->getOutput())->toContain('French: Bonjour, comment ça va?')
        ->and($result->getToolResults())->toHaveCount(2)
        ->and($result->getToolResults()[0]['toolName'])->toBe('spanish_agent')
        ->and($result->getToolResults()[1]['toolName'])->toBe('french_agent');
}); 