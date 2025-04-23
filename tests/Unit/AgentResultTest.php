<?php

use Grpaiva\PrismAgents\AgentResult;
use Grpaiva\PrismAgents\Trace;
use Grpaiva\PrismAgents\Tools\Tool;

test('it can create an agent result', function () {
    $result = new AgentResult('test-run-id', 'content');
    
    expect($result)->toBeInstanceOf(AgentResult::class);
    expect($result->content)->toBe('content');
    expect($result->runId)->toBe('test-run-id');
    expect($result->toolCalls)->toBe([]);
});

test('it can add tool calls', function () {
    $result = new AgentResult('test-run-id', 'content');
    
    $result->addToolCall('test-tool', ['param' => 'value']);
    
    expect($result->toolCalls)->toBe([
        [
            'name' => 'test-tool',
            'arguments' => ['param' => 'value'],
        ],
    ]);
});

test('it can get the last tool call', function () {
    $result = new AgentResult('test-run-id', 'content');
    
    $result->addToolCall('test-tool-1', ['param' => 'value1']);
    $result->addToolCall('test-tool-2', ['param' => 'value2']);
    
    $lastToolCall = $result->lastToolCall();
    
    expect($lastToolCall)->toBe([
        'name' => 'test-tool-2',
        'arguments' => ['param' => 'value2'],
    ]);
});

test('it returns null when getting last tool call with no tool calls', function () {
    $result = new AgentResult('test-run-id', 'content');
    
    expect($result->lastToolCall())->toBeNull();
});

test('it can convert to an array', function () {
    $result = new AgentResult('test-run-id', 'content');
    $result->addToolCall('test-tool', ['param' => 'value']);
    
    expect($result->toArray())->toBe([
        'content' => 'content',
        'run_id' => 'test-run-id',
        'tool_calls' => [
            [
                'name' => 'test-tool',
                'arguments' => ['param' => 'value'],
            ],
        ],
    ]);
});

test('it can be converted to JSON', function () {
    $result = new AgentResult('test-run-id', 'content');
    $result->addToolCall('test-tool', ['param' => 'value']);
    
    $expected = json_encode([
        'content' => 'content',
        'run_id' => 'test-run-id',
        'tool_calls' => [
            [
                'name' => 'test-tool',
                'arguments' => ['param' => 'value'],
            ],
        ],
    ]);
    
    expect($result->toJson())->toBe($expected);
});

test('it can be marked as unsafe', function () {
    $result = new AgentResult('test');
    
    $result->markAsUnsafe('unsafe reason');
    
    expect($result->isSafe())->toBeFalse();
    expect($result->getUnsafeReason())->toBe('unsafe reason');
});

test('it can create a result with output', function () {
    $result = new AgentResult('test output');
    
    expect($result->getFinalOutput())->toBe('test output');
    expect($result->getToolResults())->toBeEmpty();
});

test('it can create a result with tool results', function () {
    $toolResults = [
        ['toolName' => 'test-tool', 'result' => 'tool result']
    ];
    
    $result = new AgentResult('test output', $toolResults);
    
    expect($result->getFinalOutput())->toBe('test output');
    expect($result->getToolResults())->toBe($toolResults);
});

test('it can set and get context', function () {
    $result = new AgentResult('test output');
    $context = ['key' => 'value'];
    
    $result->setContext($context);
    
    expect($result->getContext())->toBe($context);
});

test('it can set and get span id', function () {
    $result = new AgentResult('test output');
    
    $result->setSpanId('test-span-id');
    
    expect($result->getSpanId())->toBe('test-span-id');
});

test('it can convert to array', function () {
    $toolResults = [
        ['toolName' => 'test-tool', 'result' => 'tool result']
    ];
    
    $result = new AgentResult('test output', $toolResults);
    $result->setContext(['key' => 'value']);
    $result->setSpanId('test-span-id');
    
    $array = $result->toArray();
    
    expect($array)->toHaveKeys(['output', 'toolResults', 'context', 'spanId']);
    expect($array['output'])->toBe('test output');
    expect($array['toolResults'])->toBe($toolResults);
    expect($array['context'])->toBe(['key' => 'value']);
    expect($array['spanId'])->toBe('test-span-id');
}); 