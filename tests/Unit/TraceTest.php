<?php

use Grpaiva\PrismAgents\Trace;

test('it can create a trace', function () {
    $trace = new Trace('test-run-id');
    
    expect($trace)->toBeInstanceOf(Trace::class);
    expect($trace->getRunId())->toBe('test-run-id');
    expect($trace->getEvents())->toBeArray();
    expect($trace->getEvents())->toBeEmpty();
});

test('it can add events to trace', function () {
    $trace = new Trace('test-run-id');
    
    $trace->addEvent('agent', ['message' => 'Hello World']);
    
    expect($trace->getEvents())->toHaveCount(1);
    expect($trace->getEvents()[0]['type'])->toBe('agent');
    expect($trace->getEvents()[0]['data'])->toBe(['message' => 'Hello World']);
    expect($trace->getEvents()[0]['timestamp'])->toBeNumeric();
});

test('it can add tool events to trace', function () {
    $trace = new Trace('test-run-id');
    
    $trace->addToolEvent('http', ['url' => 'https://example.com']);
    
    expect($trace->getEvents())->toHaveCount(1);
    expect($trace->getEvents()[0]['type'])->toBe('tool');
    expect($trace->getEvents()[0]['tool'])->toBe('http');
    expect($trace->getEvents()[0]['data'])->toBe(['url' => 'https://example.com']);
    expect($trace->getEvents()[0]['timestamp'])->toBeNumeric();
});

test('it can add result events to trace', function () {
    $trace = new Trace('test-run-id');
    
    $trace->addResultEvent(['content' => 'Test content']);
    
    expect($trace->getEvents())->toHaveCount(1);
    expect($trace->getEvents()[0]['type'])->toBe('result');
    expect($trace->getEvents()[0]['data'])->toBe(['content' => 'Test content']);
    expect($trace->getEvents()[0]['timestamp'])->toBeNumeric();
});

test('it can add guardrail events to trace', function () {
    $trace = new Trace('test-run-id');
    
    $trace->addGuardrailEvent(['flagged' => true]);
    
    expect($trace->getEvents())->toHaveCount(1);
    expect($trace->getEvents()[0]['type'])->toBe('guardrail');
    expect($trace->getEvents()[0]['data'])->toBe(['flagged' => true]);
    expect($trace->getEvents()[0]['timestamp'])->toBeNumeric();
}); 