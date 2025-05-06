<?php

use Grpaiva\PrismAgents\Agent;
use Prism\Prism\Enums\Provider;
use Mockery as m;
use Grpaiva\PrismAgents\Tests\TestHelpers\MocksTrait;

uses(MocksTrait::class);

test('agent can be constructed with builder pattern', function () {
    $agent = Agent::as('assistant')
        ->withInstructions('You are a helpful assistant.')
        ->using(Provider::OpenAI, 'gpt-4o');

    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($agent->getName())->toBe('assistant')
        ->and($agent->getInstructions())->toBe('You are a helpful assistant.')
        ->and($agent->getProvider())->toBe(Provider::OpenAI)
        ->and($agent->getModel())->toBe('gpt-4o');
});

test('agent can add tools', function () {
    $mockTool = $this->mockTool('mock_tool');
    
    $agent = Agent::as('assistant')
        ->withInstructions('You are a helpful assistant.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withToolChoice($mockTool)
        ->withTools([$mockTool]);

    expect($agent->getTools())->toHaveCount(1)
        ->and($agent->getTools()[0]->name())->toBe('mock_tool')
        ->and($agent->getToolChoice())->toBe($mockTool->name());
});

test('agent can be converted to a tool', function () {
    $agent = Agent::as('weather_agent')
        ->withInstructions('You are a weather specialist.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withHandoffDescription('A specialist in weather information');

    $tool = $agent->asTool();

    expect($tool)->toBeInstanceOf('Prism\Prism\Tool')
        ->and($tool->name())->toBe('weather_agent')
        ->and($tool->description())->toBe('A specialist in weather information');
});

test('agent can configure generation', function () {
    $agent = Agent::as('weather_agent')
        ->withInstructions('You are a weather specialist.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withMaxSteps(5);

    expect($agent->getMaxSteps())->toBe(5);
});

test('agent can configure models', function () {
    $agent = Agent::as('weather_agent')
        ->withInstructions('You are a weather specialist.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withMaxTokens(2048)
        ->usingTemperature(0.8)
        ->usingTopP(0.5);

    expect($agent->getMaxTokens())->toBe(2048)
        ->and($agent->getTemperature())->toBe(0.8)
        ->and($agent->getTopP())->toBe(0.5);
});

test('agent can configure client', function () {
    $agent = Agent::as('weather_agent')
        ->withInstructions('You are a weather specialist.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withClientOptions(['timeout' => 30])
        ->withClientRetry(3, 100, null, false);

    expect($agent->getClientOptions())->toBe(['timeout' => 30])
        ->and($agent->getClientRetry())->toBe([3, 100, null, false]);
});

