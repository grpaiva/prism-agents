<?php

use Grpaiva\PrismAgents\AgentContext;

test('it can create an agent context', function () {
    $context = new AgentContext([
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    ]);
    
    expect($context)->toBeInstanceOf(AgentContext::class);
    expect($context->toArray())->toBe([
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    ]);
});

test('it can add a user message', function () {
    $context = new AgentContext();
    
    $context->addUserMessage('Hello');
    
    expect($context->toArray())->toBe([
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    ]);
});

test('it can add an assistant message', function () {
    $context = new AgentContext();
    
    $context->addAssistantMessage('Hello');
    
    expect($context->toArray())->toBe([
        'messages' => [
            ['role' => 'assistant', 'content' => 'Hello'],
        ],
    ]);
});

test('it can add a system message', function () {
    $context = new AgentContext();
    
    $context->addSystemMessage('Hello');
    
    expect($context->toArray())->toBe([
        'messages' => [
            ['role' => 'system', 'content' => 'Hello'],
        ],
    ]);
});

test('it can add a tool message', function () {
    $context = new AgentContext();
    
    $context->addToolMessage('Hello', 'test-tool');
    
    expect($context->toArray())->toBe([
        'messages' => [
            [
                'role' => 'tool', 
                'content' => 'Hello',
                'tool_call_id' => 'test-tool',
            ],
        ],
    ]);
});

test('it can add multiple messages', function () {
    $context = new AgentContext();
    
    $context->addUserMessage('Hello');
    $context->addAssistantMessage('Hi');
    
    expect($context->toArray())->toBe([
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi'],
        ],
    ]);
});

test('it can be converted to JSON', function () {
    $context = new AgentContext([
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    ]);
    
    expect($context->toJson())->toBe(json_encode([
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    ]));
});

test('it can add a tool call', function () {
    $context = new AgentContext();
    
    $context->addToolCall('test-id', 'test-tool', ['param' => 'value']);
    
    expect($context->toArray())->toBe([
        'messages' => [
            [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [
                    [
                        'id' => 'test-id',
                        'type' => 'function',
                        'function' => [
                            'name' => 'test-tool',
                            'arguments' => json_encode(['param' => 'value']),
                        ],
                    ],
                ],
            ],
        ],
    ]);
});

test('it can filter messages by role', function () {
    $context = new AgentContext();
    
    $context->addUserMessage('Hello');
    $context->addAssistantMessage('Hi');
    $context->addSystemMessage('System');
    
    $userMessages = $context->getUserMessages();
    $assistantMessages = $context->getAssistantMessages();
    $systemMessages = $context->getSystemMessages();
    
    expect($userMessages)->toHaveCount(1);
    expect($userMessages[0]['content'])->toBe('Hello');
    
    expect($assistantMessages)->toHaveCount(1);
    expect($assistantMessages[0]['content'])->toBe('Hi');
    
    expect($systemMessages)->toHaveCount(1);
    expect($systemMessages[0]['content'])->toBe('System');
});

test('it can get values from context', function () {
    $context = new AgentContext(['test' => 'value', 'nested' => ['key' => 'value']]);
    
    expect($context->get('test'))->toBe('value');
    expect($context->get('nested.key'))->toBe('value');
    expect($context->get('non-existent'))->toBeNull();
    expect($context->get('non-existent', 'default'))->toBe('default');
});

test('it can set values in context', function () {
    $context = new AgentContext(['test' => 'value']);
    
    $context->set('new', 'value');
    $context->set('nested.key', 'value');
    
    expect($context->get('new'))->toBe('value');
    expect($context->get('nested.key'))->toBe('value');
    expect($context->toArray())->toBe([
        'test' => 'value',
        'new' => 'value',
        'nested' => ['key' => 'value'],
    ]);
});

test('it can merge contexts', function () {
    $context1 = new AgentContext(['test' => 'value', 'keep' => 'old']);
    $context2 = new AgentContext(['new' => 'value', 'keep' => 'new']);
    
    $merged = $context1->merge($context2);
    
    expect($merged)->toBeInstanceOf(AgentContext::class);
    expect($merged->toArray())->toBe([
        'test' => 'value',
        'keep' => 'new',
        'new' => 'value',
    ]);
});

test('it can check if key exists', function () {
    $context = new AgentContext(['key' => 'value']);
    
    expect($context->has('key'))->toBeTrue();
    expect($context->has('missing'))->toBeFalse();
});

test('it can remove a key', function () {
    $context = new AgentContext(['key' => 'value']);
    
    $context->remove('key');
    
    expect($context->has('key'))->toBeFalse();
});

test('it can create child context', function () {
    $parent = new AgentContext(['parent_key' => 'parent_value']);
    $child = $parent->createChild(['child_key' => 'child_value']);
    
    expect($child->get('parent_key'))->toBe('parent_value');
    expect($child->get('child_key'))->toBe('child_value');
    expect($child->getParent())->toBe($parent);
});

test('it prioritizes child values over parent', function () {
    $parent = new AgentContext(['key' => 'parent_value']);
    $child = $parent->createChild(['key' => 'child_value']);
    
    expect($child->get('key'))->toBe('child_value');
});

test('it can merge parent and child contexts', function () {
    $parent = new AgentContext(['parent_key' => 'parent_value', 'shared_key' => 'parent_value']);
    $child = $parent->createChild(['child_key' => 'child_value', 'shared_key' => 'child_value']);
    
    $merged = $child->merged();
    
    expect($merged)->toBe([
        'parent_key' => 'parent_value',
        'shared_key' => 'child_value',
        'child_key' => 'child_value'
    ]);
}); 