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
        ->withTools([$mockTool]);

    expect($agent->getTools())->toHaveCount(1)
        ->and($agent->getTools()[0]->name())->toBe('mock_tool');
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