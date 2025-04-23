<?php

namespace Grpaiva\PrismAgents\Tests;

use Grpaiva\PrismAgents\Guardrail;
use Grpaiva\PrismAgents\GuardrailResult;
use Grpaiva\PrismAgents\GuardrailException;
use Grpaiva\PrismAgents\AgentContext;

class TestGuardrail extends Guardrail
{
    protected $shouldPass;
    
    public function __construct(bool $shouldPass = true)
    {
        $this->shouldPass = $shouldPass;
    }
    
    public function check($input, AgentContext $context): GuardrailResult
    {
        if ($this->shouldPass) {
            return GuardrailResult::pass(['input' => $input]);
        } else {
            return GuardrailResult::fail('Failed guardrail check', 400, ['input' => $input]);
        }
    }
}
use PHPUnit\Framework\Attributes\Test;

class GuardrailTest extends TestCase
{
    #[Test]
    public function guardrail_result_can_pass()
    {
        $result = GuardrailResult::pass(['key' => 'value']);
        
        $this->assertTrue($result->passes());
        $this->assertFalse($result->fails());
        $this->assertNull($result->getMessage());
        $this->assertNull($result->getCode());
        $this->assertEquals(['key' => 'value'], $result->getData());
    }
    
    /** @test */
    public function guardrail_result_can_fail()
    {
        $result = GuardrailResult::fail('Error message', 422, ['key' => 'value']);
        
        $this->assertFalse($result->passes());
        $this->assertTrue($result->fails());
        $this->assertEquals('Error message', $result->getMessage());
        $this->assertEquals(422, $result->getCode());
        $this->assertEquals(['key' => 'value'], $result->getData());
    }
    
    /** @test */
    public function guardrail_can_be_implemented()
    {
        $context = new AgentContext();
        
        $passingGuardrail = new TestGuardrail(true);
        $failingGuardrail = new TestGuardrail(false);
        
        $passResult = $passingGuardrail->check('test input', $context);
        $failResult = $failingGuardrail->check('test input', $context);
        
        $this->assertTrue($passResult->passes());
        $this->assertFalse($failResult->passes());
        $this->assertEquals('Failed guardrail check', $failResult->getMessage());
    }
    
    /** @test */
    public function guardrail_exception_can_be_created()
    {
        $exception = new GuardrailException('Error message', 422, ['key' => 'value']);
        
        $this->assertEquals('Error message', $exception->getMessage());
        $this->assertEquals(422, $exception->getCode());
        $this->assertEquals(['key' => 'value'], $exception->getData());
    }
} 