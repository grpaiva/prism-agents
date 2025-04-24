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
