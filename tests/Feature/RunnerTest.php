<?php

use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\AgentContext;
use Grpaiva\PrismAgents\Runner;
use Grpaiva\PrismAgents\Tool;
use Grpaiva\PrismAgents\Trace;
use Grpaiva\PrismAgents\Guardrail;
use Grpaiva\PrismAgents\GuardrailResult;
use Grpaiva\PrismAgents\GuardrailException;
use Mockery\MockInterface;
use Mockery\LegacyMockInterface;

test('it can run a simple agent', function () {
    // Mock the Prism facade response
    mockPrismResponse('This is a test response');
    
    $agent = new Agent('test-agent', 'You are a test agent');
    $runner = new Runner();
    
    $result = $runner->runAgent($agent, 'test input');
    
    expect($result->getFinalOutput())->toBe('This is a test response');
})->group('runner');

test('it can run an agent with tools', function () {
    // Create a mock response with tool results
    $responseData = (object)[
        'text' => 'Tool was used successfully',
        'toolResults' => [
            (object)[
                'toolName' => 'test-tool',
                'result' => 'tool result data'
            ]
        ],
        'steps' => [
            (object)[
                'type' => 'tool_call',
                'name' => 'test-tool',
            ]
        ]
    ];
    
    mockPrismResponse($responseData);
    
    $tool = new Tool('test-tool', 'A test tool', function ($args) {
        return 'tool result';
    });
    
    $agent = new Agent('test-agent', 'You are a test agent', [
        'tools' => [$tool]
    ]);
    
    $runner = new Runner();
    $result = $runner->runAgent($agent, 'use the test tool');
    
    expect($result->getFinalOutput())->toBe('Tool was used successfully');
    expect($result->getToolResults())->toHaveCount(1);
    expect($result->getToolResults()[0]['toolName'])->toBe('test-tool');
    expect($result->getToolResults()[0]['result'])->toBe('tool result data');
})->group('runner');

test('it throws exception when guardrail fails', function () {
    $agent = new Agent('test-agent', 'You are a test agent');
    
    // Add a failing guardrail
    $guardrail = Mockery::mock(Guardrail::class);
    $guardrail->shouldReceive('check')
        ->once()
        ->andReturn(GuardrailResult::fail('Guardrail check failed', 400));
    
    $agent->addGuardrail($guardrail);
    
    $runner = new Runner();
    
    expect(fn() => $runner->runAgent($agent, 'test input'))
        ->toThrow(GuardrailException::class, 'Guardrail check failed');
})->group('runner');

test('it uses tracing for spans', function () {
    mockPrismResponse('Test response');
    
    $trace = Mockery::mock(Trace::class);
    $trace->shouldReceive('startSpan')
        ->once()
        ->with('test-agent', 'agent_run')
        ->andReturn('test-span-id');
    
    $trace->shouldReceive('endSpan')
        ->once()
        ->with('test-span-id', Mockery::type('array'))
        ->andReturnSelf();
    
    $agent = new Agent('test-agent', 'You are a test agent');
    $runner = new Runner($trace);
    
    $result = $runner->runAgent($agent, 'test input');
    
    expect($result->getFinalOutput())->toBe('Test response');
})->group('runner');

test('it can run an agent with array input', function () {
    mockPrismResponse('Response to conversation');
    
    $agent = new Agent('test-agent', 'You are a test agent');
    $runner = new Runner();
    
    $input = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
        ['role' => 'user', 'content' => 'How are you?']
    ];
    
    $result = $runner->runAgent($agent, $input);
    
    expect($result->getFinalOutput())->toBe('Response to conversation');
})->group('runner');

afterEach(function () {
    Mockery::close();
}); 