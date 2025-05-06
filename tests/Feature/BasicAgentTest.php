<?php

use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\AgentResult;
use Grpaiva\PrismAgents\Tests\TestHelpers\MocksTrait;
use Grpaiva\PrismAgents\Tests\TestHelpers\PrismMockTrait;
use Mockery as m;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Tool;

uses(MocksTrait::class, PrismMockTrait::class);

// Create an agent for testing
beforeEach(function () {
    $this->agent = Agent::as('assistant')
        ->withInstructions('You are a helpful assistant.')
        ->using(Provider::OpenAI, 'gpt-4o');

    // Create a mock result
    $this->result = m::mock(AgentResult::class);
    $this->result->shouldReceive('getOutput')->andReturn('This is a mocked response from the AI.');
    $this->result->shouldReceive('getTokensUsed')->andReturn(70);
    $this->result->shouldReceive('getToolResults')->andReturn([]);
    $this->result->shouldReceive('withTrace')->andReturnSelf();
});

test('agent can be created correctly', function () {
    expect($this->agent)->toBeInstanceOf(Agent::class)
        ->and($this->agent->getName())->toBe('assistant')
        ->and($this->agent->getInstructions())->toBe('You are a helpful assistant.')
        ->and($this->agent->getProvider())->toBe(Provider::OpenAI)
        ->and($this->agent->getModel())->toBe('gpt-4o');
});

test('agent with tools can be created correctly', function () {
    // Mock a weather tool
    $weatherTool = $this->mockTool('get_weather', 'Get the current weather for a location');

    // Create an agent with the tool
    $agent = Agent::as('weather_assistant')
        ->withInstructions('You are a helpful assistant that can check the weather.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withTools([$weatherTool]);

    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($agent->getName())->toBe('weather_assistant')
        ->and($agent->getTools())->toHaveCount(1)
        ->and($agent->getTools()[0]->name())->toBe('get_weather');
});
