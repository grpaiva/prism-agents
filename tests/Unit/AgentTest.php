<?php

use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\Tool;

test('it can create an agent', function () {
    $agent = new Agent('test-agent', 'You are a test agent');
    
    expect($agent->getName())->toBe('test-agent');
    expect($agent->getInstructions())->toBe('You are a test agent');
    expect($agent->getHandoffDescription())->toBeNull();
    expect($agent->getTools())->toBeEmpty();
});

test('it can create an agent with config', function () {
    $agent = new Agent('test-agent', 'You are a test agent', [
        'handoffDescription' => 'A test agent for handoff',
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);
    
    expect($agent->getName())->toBe('test-agent');
    expect($agent->getInstructions())->toBe('You are a test agent');
    expect($agent->getHandoffDescription())->toBe('A test agent for handoff');
});

test('it can add tools to agent', function () {
    $agent = new Agent('test-agent', 'You are a test agent');
    
    $tool = new Tool('test-tool', 'A test tool', function ($args) {
        return 'Tool executed with: ' . json_encode($args);
    });
    
    $agent->addTool($tool);
    
    expect($agent->getTools())->toHaveCount(1);
    expect($agent->getTools()[0])->toBe($tool);
});

test('it can add multiple tools to agent', function () {
    $agent = new Agent('test-agent', 'You are a test agent');
    
    $tool1 = new Tool('test-tool-1', 'A test tool 1', function ($args) {
        return 'Tool 1 executed with: ' . json_encode($args);
    });
    
    $tool2 = new Tool('test-tool-2', 'A test tool 2', function ($args) {
        return 'Tool 2 executed with: ' . json_encode($args);
    });
    
    $agent->addTools([$tool1, $tool2]);
    
    expect($agent->getTools())->toHaveCount(2);
});

test('it can be converted to a tool', function () {
    $agent = new Agent('test-agent', 'You are a test agent', [
        'handoffDescription' => 'A test agent for handoff'
    ]);
    
    $tool = $agent->asTool('agent-tool', 'Tool that uses the test agent');
    
    expect($tool->getName())->toBe('agent-tool');
    expect($tool->getDescription())->toBe('Tool that uses the test agent');
}); 