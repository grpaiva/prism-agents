<?php

namespace Grpaiva\PrismAgents\Tests;

use Grpaiva\PrismAgents\Facades\PrismAgents;
use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\Tool;
use Grpaiva\PrismAgents\Trace;
use Grpaiva\PrismAgents\AgentContext;
use Grpaiva\PrismAgents\AgentResult;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class PrismAgentsFacadeTest extends TestCase
{
    #[Test]
    public function it_can_create_an_agent()
    {
        $agent = PrismAgents::agent('test-agent', 'You are a test agent');
        
        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertEquals('test-agent', $agent->getName());
        $this->assertEquals('You are a test agent', $agent->getInstructions());
    }
    
    /** @test */
    public function it_can_create_a_tool()
    {
        $handler = function ($args) {
            return 'Result: ' . $args['input'];
        };
        
        $tool = PrismAgents::tool('test-tool', 'A test tool', $handler);
        
        $this->assertInstanceOf(Tool::class, $tool);
        $this->assertEquals('test-tool', $tool->getName());
        $this->assertEquals('A test tool', $tool->getDescription());
    }
    
    /** @test */
    public function it_can_create_a_trace()
    {
        $trace = PrismAgents::trace();
        
        $this->assertInstanceOf(Trace::class, $trace);
        $this->assertNotNull($trace->getTraceId());
        
        $specificTrace = PrismAgents::trace('specific-id');
        $this->assertEquals('specific-id', $specificTrace->getTraceId());
    }
    
    /** @test */
    public function it_can_create_a_context()
    {
        $context = PrismAgents::context(['key' => 'value']);
        
        $this->assertInstanceOf(AgentContext::class, $context);
        $this->assertEquals('value', $context->get('key'));
        
        $parent = PrismAgents::context(['parent_key' => 'parent_value']);
        $child = PrismAgents::context(['child_key' => 'child_value'], $parent);
        
        $this->assertEquals('parent_value', $child->get('parent_key'));
        $this->assertSame($parent, $child->getParent());
    }
    
    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function it_can_run_an_agent()
    {
        // Set up our expectation on the Run class's static method
        $mockResult = new AgentResult();
        $mockResult->setFinalOutput('This is a mock result');
        
        $runnerMock = Mockery::mock('alias:Grpaiva\PrismAgents\Runner');
        $runnerMock->shouldReceive('run')
            ->once()
            ->andReturn($mockResult);
        
        $agent = PrismAgents::agent('test-agent', 'You are a test agent');
        $result = PrismAgents::run($agent, 'test input');
        
        $this->assertInstanceOf(AgentResult::class, $result);
        $this->assertEquals('This is a mock result', $result->getFinalOutput());
    }
    
    /** @test */
    public function it_can_map_provider_names()
    {
        $openaiProvider = PrismAgents::mapProvider('openai');
        $this->assertEquals('openai', strtolower((string)$openaiProvider));
        
        $anthropicProvider = PrismAgents::mapProvider('anthropic');
        $this->assertEquals('anthropic', strtolower((string)$anthropicProvider));
    }
    
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
} 