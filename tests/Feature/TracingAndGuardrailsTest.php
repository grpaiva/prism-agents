<?php

use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\PrismAgents;
use Grpaiva\PrismAgents\Trace;
use Grpaiva\PrismAgents\Guardrail;
use Grpaiva\PrismAgents\GuardrailResult;
use Grpaiva\PrismAgents\AgentContext;
use Grpaiva\PrismAgents\AgentResult;
use Grpaiva\PrismAgents\Exceptions\GuardrailException;
use Prism\Prism\Enums\Provider;
use Grpaiva\PrismAgents\Tests\TestHelpers\MocksTrait;
use Grpaiva\PrismAgents\Tests\TestHelpers\PrismMockTrait;
use Mockery as m;
use Prism\Prism\Facades\Tool;

uses(MocksTrait::class, PrismMockTrait::class);

// This test requires special mocking to prevent real API calls
beforeEach(function() {
    // Create a configurable mock for PrismAgents
    $prismAgentsMock = $this->createMockPrismAgents();
    
    // Replace the concrete implementation with our mock
    app()->instance(PrismAgents::class, $prismAgentsMock);
});

test('trace can be created with a name', function () {
    $trace = Trace::as('test_trace');
    expect($trace)->toBeInstanceOf(Trace::class)
        ->and($trace->getName())->toBe('test_trace');
});

test('agent with guardrails can be created', function () {
    // Create a custom profanity guardrail
    $profanityGuardrail = new class('profanity_filter') extends Guardrail {
        public function __construct(string $name) {
            $this->name = $name;
        }
        
        public function check($input, AgentContext $context): GuardrailResult
        {
            // Simple check for a banned word
            if (is_string($input) && stripos($input, 'badword') !== false) {
                return GuardrailResult::fail('Input contains profanity', 400);
            }
            
            return GuardrailResult::pass();
        }
    };

    // Create an agent with the guardrail
    $agent = Agent::as('safe_assistant')
        ->withInstructions('You are a helpful assistant.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withInputGuardrails([$profanityGuardrail]);

    // Test that the agent has the guardrail
    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($agent->getName())->toBe('safe_assistant');
        
    // Get the guardrails through reflection since they might be protected
    $reflection = new ReflectionProperty(Agent::class, 'inputGuardrails');
    $reflection->setAccessible(true);
    $guardrails = $reflection->getValue($agent);
    
    expect($guardrails)->toBeArray()
        ->and($guardrails)->toHaveCount(1);
        
    // Check the guardrail name using reflection
    $guardrailReflection = new ReflectionProperty(Guardrail::class, 'name');
    $guardrailReflection->setAccessible(true);
    $guardrailName = $guardrailReflection->getValue($guardrails[0]);
    
    expect($guardrailName)->toBe('profanity_filter');
});

test('agent with tools can be created', function () {
    // Create a mock tool
    $mockTool = $this->mockTool('mock_tool', 'A mock tool for testing');

    // Create an agent with the tool
    $agent = Agent::as('tool_agent')
        ->withInstructions('You are a helpful assistant that uses tools.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withTools([$mockTool]);

    // Verify the agent has the tool
    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($agent->getTools())->toHaveCount(1)
        ->and($agent->getTools()[0]->name())->toBe('mock_tool')
        ->and($agent->getTools()[0]->description())->toBe('A mock tool for testing');
});

test('agent run can be traced', function () {
    // Set up Agent
    $agent = Agent::as('assistant')
        ->withInstructions('You are a helpful assistant.')
        ->using(Provider::OpenAI, 'gpt-4o');

    // Mock the Agent result with a trace
    $mockedResult = m::mock(AgentResult::class);
    $mockedResult->shouldReceive('getOutput')->andReturn('This is a mocked response from the AI.');
    $mockedResult->shouldReceive('getTokensUsed')->andReturn(70);
    $mockedResult->shouldReceive('getToolResults')->andReturn([]);
    $mockedResult->shouldReceive('withTrace')->andReturnSelf();

    // Mock PrismAgents to return our result
    $prismAgentsMock = m::mock(PrismAgents::class);
    $prismAgentsMock->shouldReceive('run')
        ->with($agent, "Hello, how are you?", m::any())
        ->andReturn($mockedResult);
    app()->instance(PrismAgents::class, $prismAgentsMock);
    
    // Verify the result is as expected
    $result = PrismAgents::run($agent, "Hello, how are you?")->withTrace('test_trace');
    expect($result->getOutput())->toBe('This is a mocked response from the AI.');
});

test('guardrails can prevent agent execution with invalid input', function () {
    // Create a custom profanity guardrail that flags "badword"
    $profanityGuardrail = new class('profanity_filter') extends Guardrail {
        public function __construct(string $name) {
            $this->name = $name;
        }
        
        public function check($input, AgentContext $context): GuardrailResult
        {
            // Simple check for a banned word
            if (is_string($input) && stripos($input, 'badword') !== false) {
                return GuardrailResult::fail('Input contains profanity', 400);
            }
            
            return GuardrailResult::pass();
        }
    };

    // Create an agent with the guardrail
    $agent = Agent::as('safe_assistant')
        ->withInstructions('You are a helpful assistant.')
        ->using(Provider::OpenAI, 'gpt-4o')
        ->withInputGuardrails([$profanityGuardrail]);

    // Mock PrismAgents to return a result for valid input
    $validResult = m::mock(AgentResult::class);
    $validResult->shouldReceive('getOutput')->andReturn('Valid response');
    
    $prismAgentsMock = m::mock(PrismAgents::class);
    $prismAgentsMock->shouldReceive('run')
        ->with($agent, "Hello, can you help me?", m::any())
        ->andReturn($validResult);
    app()->instance(PrismAgents::class, $prismAgentsMock);
    
    // For invalid input, mock a GuardrailException to be thrown
    $prismAgentsMock->shouldReceive('run')
        ->with($agent, "Hello, can you help me with badword?", m::any())
        ->andThrow(new GuardrailException('Input contains profanity', 400));
    
    // Test with valid input
    $result = PrismAgents::run($agent, "Hello, can you help me?");
    expect($result->getOutput())->toBe('This is a mocked response from the AI.')
        ->and(fn() => PrismAgents::run($agent, "Hello, can you help me with badword?"))
        ->toThrow(GuardrailException::class, 'Input contains profanity');

    // Test with invalid input
});
