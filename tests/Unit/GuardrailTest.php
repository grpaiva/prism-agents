<?php

use Grpaiva\PrismAgents\AgentContext;
use Grpaiva\PrismAgents\Guardrail;
use Grpaiva\PrismAgents\GuardrailResult;
use Mockery as m;

test('guardrail can be created and pass input validation', function () {
    $context = m::mock(AgentContext::class);

    $guardrail = new class('test_guardrail') extends Guardrail
    {
        public function __construct(string $name)
        {
            $this->name = $name;
        }

        public function check($input, AgentContext $context): GuardrailResult
        {
            // Simple validation - input must be at least 5 characters
            if (is_string($input) && strlen($input) < 5) {
                return GuardrailResult::fail('Input must be at least 5 characters', 400);
            }

            return GuardrailResult::pass();
        }
    };

    $result = $guardrail->check('Hello world', $context);
    expect($result)->toBeInstanceOf(GuardrailResult::class)
        ->and($result->passes())->toBeTrue()
        ->and($result->getMessage())->toBeNull()
        ->and($result->getCode())->toBeNull();
});

test('guardrail can detect invalid input', function () {
    $context = m::mock(AgentContext::class);

    $guardrail = new class('test_guardrail') extends Guardrail
    {
        public function __construct(string $name)
        {
            $this->name = $name;
        }

        public function check($input, AgentContext $context): GuardrailResult
        {
            // Simple validation - input must be at least 5 characters
            if (is_string($input) && strlen($input) < 5) {
                return GuardrailResult::fail('Input must be at least 5 characters', 400);
            }

            return GuardrailResult::pass();
        }
    };

    $result = $guardrail->check('Hi', $context);
    expect($result)->toBeInstanceOf(GuardrailResult::class)
        ->and($result->passes())->toBeFalse()
        ->and($result->getMessage())->toBe('Input must be at least 5 characters')
        ->and($result->getCode())->toBe(400);
});

test('guardrail can be created with name', function () {
    // Create a custom guardrail class with public name property for testing
    $guardrail = new class('profanity_filter') extends Guardrail
    {
        public string $testName;

        public function __construct(string $name)
        {
            $this->name = $name;
            $this->testName = $name;
        }

        public function check($input, AgentContext $context): GuardrailResult
        {
            return GuardrailResult::pass();
        }
    };

    expect($guardrail)->toBeInstanceOf(Guardrail::class)
        ->and($guardrail->testName)->toBe('profanity_filter');
});
