<?php

use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\Tool;

test('it can create a tool', function () {
    $handler = function ($args) {
        return 'Result: ' . $args['input'];
    };
    
    $tool = new Tool('test-tool', 'A test tool', $handler);
    
    expect($tool->getName())->toBe('test-tool');
    expect($tool->getDescription())->toBe('A test tool');
});

test('it can create a tool with parameters', function () {
    $handler = function ($args) {
        return 'Result: ' . $args['input'];
    };
    
    $parameters = [
        'type' => 'object',
        'properties' => [
            'input' => [
                'type' => 'string',
                'description' => 'The input to process'
            ]
        ],
        'required' => ['input']
    ];
    
    $tool = new Tool('test-tool', 'A test tool', $handler, $parameters);
    
    expect($tool->getName())->toBe('test-tool');
    expect($tool->getDescription())->toBe('A test tool');
    expect($tool->getParameters())->toBe($parameters);
});

test('it can execute a function tool', function () {
    $handler = function ($args) {
        return 'Result: ' . $args['input'];
    };
    
    $tool = new Tool('test-tool', 'A test tool', $handler);
    
    $result = $tool->execute(['input' => 'test input']);
    
    expect($result)->toBe('Result: test input');
});

test('it generates correct definition', function () {
    $handler = function ($args) {
        return 'Result: ' . $args['input'];
    };
    
    $parameters = [
        'type' => 'object',
        'properties' => [
            'input' => [
                'type' => 'string',
                'description' => 'The input to process'
            ]
        ],
        'required' => ['input']
    ];
    
    $tool = new Tool('test-tool', 'A test tool', $handler, $parameters);
    
    $definition = $tool->toDefinition();
    
    expect($definition['type'])->toBe('function');
    expect($definition['function']['name'])->toBe('test-tool');
    expect($definition['function']['description'])->toBe('A test tool');
    expect($definition['function']['parameters'])->toBe($parameters);
});

test('it defaults parameters if none provided', function () {
    $handler = function ($args) {
        return 'Result: ' . $args['input'];
    };
    
    $tool = new Tool('test-tool', 'A test tool', $handler);
    
    $definition = $tool->toDefinition();
    
    expect($definition['function'])->toHaveKey('parameters');
    expect($definition['function']['parameters'])->toBe(['type' => 'object', 'properties' => []]);
}); 