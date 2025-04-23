<?php

namespace Grpaiva\PrismAgents\Tests;

use Grpaiva\PrismAgents\AgentResult;
use PHPUnit\Framework\Attributes\Test;

class AgentResultTest extends TestCase
{
    #[Test]
    public function it_can_set_and_get_final_output()
    {
        $result = new AgentResult();
        
        $result->setFinalOutput('test output');
        
        $this->assertEquals('test output', $result->getFinalOutput());
    }
    
    /** @test */
    public function it_can_add_and_get_tool_results()
    {
        $result = new AgentResult();
        
        $result->addToolResult('tool1', 'result1');
        $result->addToolResult('tool2', ['key' => 'value']);
        
        $toolResults = $result->getToolResults();
        
        $this->assertCount(2, $toolResults);
        $this->assertEquals('tool1', $toolResults[0]['toolName']);
        $this->assertEquals('result1', $toolResults[0]['result']);
        $this->assertEquals('tool2', $toolResults[1]['toolName']);
        $this->assertEquals(['key' => 'value'], $toolResults[1]['result']);
    }
    
    /** @test */
    public function it_can_add_and_get_steps()
    {
        $result = new AgentResult();
        $step1 = ['type' => 'thinking', 'content' => 'step 1'];
        $step2 = ['type' => 'action', 'content' => 'step 2'];
        
        $result->addStep($step1);
        $result->addStep($step2);
        
        $steps = $result->getSteps();
        
        $this->assertCount(2, $steps);
        $this->assertEquals($step1, $steps[0]);
        $this->assertEquals($step2, $steps[1]);
    }
    
    /** @test */
    public function it_can_set_and_get_structured_output()
    {
        $result = new AgentResult();
        $structured = ['key' => 'value', 'nested' => ['item1', 'item2']];
        
        $result->setStructuredOutput($structured);
        
        $this->assertEquals($structured, $result->getStructuredOutput());
    }
    
    /** @test */
    public function it_can_check_if_has_final_output()
    {
        $result = new AgentResult();
        
        $this->assertFalse($result->hasFinalOutput());
        
        $result->setFinalOutput('test output');
        
        $this->assertTrue($result->hasFinalOutput());
    }
    
    /** @test */
    public function it_can_convert_to_input_list()
    {
        $result = new AgentResult();
        
        $result->setFinalOutput('final text');
        $result->addToolResult('tool1', 'result1');
        $result->addToolResult('tool2', ['key' => 'value']);
        
        $inputList = $result->toInputList();
        
        $this->assertCount(3, $inputList);
        $this->assertEquals('assistant', $inputList[0]['role']);
        $this->assertEquals('final text', $inputList[0]['content']);
        $this->assertEquals('tool', $inputList[1]['role']);
        $this->assertEquals('tool1', $inputList[1]['toolName']);
        $this->assertEquals('result1', $inputList[1]['content']);
    }
    
    /** @test */
    public function it_can_convert_to_array()
    {
        $result = new AgentResult();
        
        $result->setFinalOutput('final text');
        $result->addToolResult('tool1', 'result1');
        $result->setStructuredOutput(['key' => 'value']);
        
        $array = $result->toArray();
        
        $this->assertArrayHasKey('finalOutput', $array);
        $this->assertArrayHasKey('toolResults', $array);
        $this->assertArrayHasKey('steps', $array);
        $this->assertArrayHasKey('structuredOutput', $array);
        $this->assertEquals('final text', $array['finalOutput']);
        $this->assertEquals(['key' => 'value'], $array['structuredOutput']);
    }
} 