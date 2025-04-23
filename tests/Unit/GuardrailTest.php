<?php

use Grpaiva\PrismAgents\Guardrail;
use Grpaiva\PrismAgents\AgentResult;
use Grpaiva\PrismAgents\Trace;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;
use Mockery\LegacyMockInterface;

test('it can create a guardrail', function () {
    $guardrail = new Guardrail();
    
    expect($guardrail)->toBeInstanceOf(Guardrail::class);
});

test('it can accept a result without flagging', function () {
    $guardrail = new Guardrail();
    $result = new AgentResult('This is a safe output');
    
    $flagged = $guardrail->check($result);
    
    expect($flagged)->toBeFalse();
});

test('it can flag a result with unsafe output', function () {
    $guardrail = new Guardrail();
    $result = new AgentResult('This output contains a bad word: @#$%');
    
    $flagged = $guardrail->check($result);
    
    expect($flagged)->toBeTrue();
});

test('it can flag a result with unsafe tool result', function () {
    $guardrail = new Guardrail();
    $toolResults = [
        ['toolName' => 'test-tool', 'result' => 'This tool result contains a bad word: @#$%']
    ];
    $result = new AgentResult('Safe output', $toolResults);
    
    $flagged = $guardrail->check($result);
    
    expect($flagged)->toBeTrue();
});

test('it adds traces when checking a result', function () {
    $guardrail = new Guardrail();
    $result = new AgentResult('This is a safe output');
    $trace = new Trace();
    
    $guardrail->check($result, $trace);
    
    expect($trace->getEvents())->toHaveCount(1);
    expect($trace->getEvents()[0]['type'])->toBe('guardrail');
});

test('it can enable and disable guardrails', function () {
    Config::set('prism-agents.guardrails.enabled', true);
    expect(Guardrail::isEnabled())->toBeTrue();
    
    Config::set('prism-agents.guardrails.enabled', false);
    expect(Guardrail::isEnabled())->toBeFalse();
});

test('it can check for unsafe content', function () {
    Config::set('prism-agents.guardrails.enabled', true);
    Config::set('prism-agents.guardrails.api_key', 'fake-api-key');
    
    $result = new AgentResult([
        'id' => '123',
        'content' => 'This is a safe content',
    ]);
    
    $guardrail = new Guardrail();
    $guardrail->setHttpClient(mockGuardrailHttpClient(false));
    
    $checkedResult = $guardrail->check($result);
    
    expect($checkedResult)->toBe($result);
    expect($checkedResult->isUnsafe())->toBeFalse();
});

test('it can detect unsafe content', function () {
    Config::set('prism-agents.guardrails.enabled', true);
    Config::set('prism-agents.guardrails.api_key', 'fake-api-key');
    
    $result = new AgentResult([
        'id' => '123',
        'content' => 'This content contains unsafe material',
    ]);
    
    $guardrail = new Guardrail();
    $guardrail->setHttpClient(mockGuardrailHttpClient(true, 'Contains harmful content'));
    
    $checkedResult = $guardrail->check($result);
    
    expect($checkedResult)->toBe($result);
    expect($checkedResult->isUnsafe())->toBeTrue();
    expect($checkedResult->unsafeReason())->toBe('Contains harmful content');
});

test('it doesnt check when disabled', function () {
    Config::set('prism-agents.guardrails.enabled', false);
    
    $result = new AgentResult([
        'id' => '123',
        'content' => 'Some content',
    ]);
    
    $guardrail = new Guardrail();
    
    $checkedResult = $guardrail->check($result);
    
    expect($checkedResult)->toBe($result);
    expect($checkedResult->isUnsafe())->toBeFalse();
});

function mockGuardrailHttpClient(bool $isUnsafe, string $reason = null) {
    $mockClient = Mockery::mock('GuzzleHttp\Client');
    
    $response = [
        'results' => [
            [
                'flagged' => $isUnsafe,
                'categories' => $isUnsafe ? ['harmful_content' => true] : [],
                'category_scores' => $isUnsafe ? ['harmful_content' => 0.9] : [],
            ]
        ]
    ];
    
    $mockResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
    $mockResponse->shouldReceive('getBody->getContents')
        ->andReturn(json_encode($response));
    
    $mockClient->shouldReceive('post')
        ->andReturn($mockResponse);
    
    return $mockClient;
} 