<?php

namespace Grpaiva\PrismAgents\Tests;

use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\Tool;
use PHPUnit\Framework\Attributes\Test;

class AgentTest extends TestCase
{
    #[Test]
    public function it_can_create_an_agent()
    {
        $agent = new Agent('test-agent', 'You are a test agent');
        
        $this->assertEquals('test-agent', $agent->getName());
        $this->assertEquals('You are a test agent', $agent->getInstructions());
        $this->assertNull($agent->getHandoffDescription());
        $this->assertEmpty($agent->getTools());
    }
    
    /** @test */
    public function it_can_create_an_agent_with_config()
    {
        $agent = new Agent('test-agent', 'You are a test agent', [
            'handoffDescription' => 'A test agent for handoff',
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);
        
        $this->assertEquals('test-agent', $agent->getName());
        $this->assertEquals('You are a test agent', $agent->getInstructions());
        $this->assertEquals('A test agent for handoff', $agent->getHandoffDescription());
    }
    
    /** @test */
    public function it_can_add_tools_to_agent()
    {
        $agent = new Agent('test-agent', 'You are a test agent');
        
        $tool = new Tool('test-tool', 'A test tool', function ($args) {
            return 'Tool executed with: ' . json_encode($args);
        });
        
        $agent->addTool($tool);
        
        $this->assertCount(1, $agent->getTools());
        $this->assertSame($tool, $agent->getTools()[0]);
    }
    
    /** @test */
    public function it_can_add_multiple_tools_to_agent()
    {
        $agent = new Agent('test-agent', 'You are a test agent');
        
        $tool1 = new Tool('test-tool-1', 'A test tool 1', function ($args) {
            return 'Tool 1 executed with: ' . json_encode($args);
        });
        
        $tool2 = new Tool('test-tool-2', 'A test tool 2', function ($args) {
            return 'Tool 2 executed with: ' . json_encode($args);
        });
        
        $agent->addTools([$tool1, $tool2]);
        
        $this->assertCount(2, $agent->getTools());
    }
    
    /** @test */
    public function it_can_be_converted_to_a_tool()
    {
        $agent = new Agent('test-agent', 'You are a test agent', [
            'handoffDescription' => 'A test agent for handoff'
        ]);
        
        $tool = $agent->asTool('agent-tool', 'Tool that uses the test agent');
        
        $this->assertEquals('agent-tool', $tool->getName());
        $this->assertEquals('Tool that uses the test agent', $tool->getDescription());
    }
} 