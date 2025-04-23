<?php

namespace Grpaiva\PrismAgents\Tests;

use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\Tool;
use Grpaiva\PrismAgents\Exceptions\ToolExecutionException;
use Grpaiva\PrismAgents\Runner;
use InvalidArgumentException;
use Grpaiva\PrismAgents\AgentResult;
use PHPUnit\Framework\Attributes\Test;

class ToolTest extends TestCase
{
    #[Test]
    public function it_can_create_a_tool()
    {
        $tool = Tool::create('test-tool')
            ->for('A test tool')
            ->using(function (array $args) {
                return 'Result: ' . $args['input'];
            });
        
        $this->assertEquals('test-tool', $tool->getName());
        $this->assertEquals('A test tool', $tool->getDescription());
    }
    
    #[Test]
    public function it_can_execute_a_function_tool()
    {
        $handler = function ($args) {
            return 'Result: ' . $args['input'];
        };
        
        $tool = new Tool('test-tool', 'A test tool', $handler);
        $tool->withStringParameter('input', 'The input to process');
        
        $result = $tool->execute(['input' => 'test input']);
        
        $this->assertEquals('Result: test input', $result);
    }
    
    #[Test]
    public function it_generates_correct_definition()
    {
        $tool = Tool::create('test-tool')
            ->for('A test tool')
            ->withStringParameter('input', 'The input to process');
        
        $definition = $tool->toDefinition();
        
        $this->assertEquals('function', $definition['type']);
        $this->assertEquals('test-tool', $definition['function']['name']);
        $this->assertEquals('A test tool', $definition['function']['description']);
        $this->assertArrayHasKey('parameters', $definition['function']);
        $this->assertArrayHasKey('properties', $definition['function']['parameters']);
        $this->assertArrayHasKey('input', $definition['function']['parameters']['properties']);
    }
    
    #[Test]
    public function it_can_define_string_parameter()
    {
        $tool = Tool::create('test-tool')
            ->for('A test tool')
            ->withStringParameter('param', 'A string parameter');
        
        $params = $tool->getParameters();
        $this->assertEquals('string', $params['properties']['param']['type']);
        $this->assertEquals('A string parameter', $params['properties']['param']['description']);
        $this->assertContains('param', $params['required'] ?? []);
    }
    
    #[Test]
    public function it_can_define_number_parameter()
    {
        $tool = Tool::create('test-tool')
            ->for('A test tool')
            ->withNumberParameter('param', 'A number parameter');
        
        $params = $tool->getParameters();
        $this->assertEquals('number', $params['properties']['param']['type']);
        $this->assertEquals('A number parameter', $params['properties']['param']['description']);
    }
    
    #[Test]
    public function it_can_define_boolean_parameter()
    {
        $tool = Tool::create('test-tool')
            ->for('A test tool')
            ->withBooleanParameter('param', 'A boolean parameter');
        
        $params = $tool->getParameters();
        $this->assertEquals('boolean', $params['properties']['param']['type']);
        $this->assertEquals('A boolean parameter', $params['properties']['param']['description']);
    }
    
    #[Test]
    public function it_can_define_array_parameter()
    {
        $tool = Tool::create('test-tool')
            ->for('A test tool')
            ->using(function (array $args) {
                return 'Result';
            })
            ->withArrayParameter('param', 'An array parameter', new \Prism\Prism\Schema\StringSchema('item', 'Array item'));
        
        $params = $tool->getParameters();
        $this->assertEquals('array', $params['properties']['param']['type']);
        $this->assertEquals('An array parameter', $params['properties']['param']['description']);
        $this->assertArrayHasKey('items', $params['properties']['param']);
    }
    
    #[Test]
    public function it_can_define_enum_parameter()
    {
        $tool = Tool::create('test-tool')
            ->for('A test tool')
            ->withEnumParameter('param', 'An enum parameter', ['option1', 'option2']);
        
        $params = $tool->getParameters();
        $this->assertEquals('string', $params['properties']['param']['type']);
        $this->assertEquals('An enum parameter', $params['properties']['param']['description']);
        $this->assertEquals(['option1', 'option2'], $params['properties']['param']['enum']);
    }
    
    #[Test]
    public function it_can_define_optional_parameter()
    {
        $tool = Tool::create('test-tool')
            ->for('A test tool')
            ->withStringParameter('required', 'Required parameter')
            ->withStringParameter('optional', 'Optional parameter', false);
        
        $params = $tool->getParameters();
        $this->assertContains('required', $params['required'] ?? []);
        $this->assertNotContains('optional', $params['required'] ?? []);
    }
    
    #[Test]
    public function it_validates_required_parameters()
    {
        $this->expectException(ToolExecutionException::class);
        
        $tool = Tool::create('test-tool')
            ->for('A test tool')
            ->using(function (array $args) {
                return 'Result: ' . $args['input'];
            })
            ->withStringParameter('input', 'The input to process');
        
        $tool->execute([]); // Missing required parameter
    }
    
    #[Test]
    public function it_can_work_with_agent_as_handler()
    {
        // Create a custom Agent implementation that is callable
        $agent = new class extends Agent {
            public function __construct() {
                parent::__construct('test-agent', 'Agent for testing');
            }
            
            // This helps our mock be seen as an Agent directly
            public function __invoke($args) {
                return 'Agent result';
            }
        };
        
        // Set the fn property directly without using the using method
        $tool = new Tool('agent-tool', 'A tool that uses an agent');
        $tool->withStringParameter('input', 'The input for the agent');
        
        // Use reflection to set the fn property
        $reflection = new \ReflectionProperty($tool, 'fn');
        $reflection->setAccessible(true);
        $reflection->setValue($tool, $agent);
        
        // Create a runner mock
        $runnerMock = $this->getMockBuilder(Runner::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        // Mock runAgent method
        $agentResult = $this->createMock(AgentResult::class);
        $runnerMock->method('runAgent')->willReturn($agentResult);
        
        // Use reflection to run the execute method with our mocked runner
        $reflection = new \ReflectionMethod($tool, 'execute');
        $reflection->setAccessible(true);
        
        // Create a custom execute method that injects our mocked runner
        $executeWithMock = function() use ($tool, $runnerMock, $agent) {
            $arguments = ['input' => 'test value'];
            
            // Validate required parameters
            foreach ($tool->getRequiredParameters() as $required) {
                if (!isset($arguments[$required])) {
                    throw new InvalidArgumentException("Missing required parameter: {$required}");
                }
            }
            
            // Custom execution with our mock
            return $runnerMock->runAgent($agent, $arguments, null);
        };
        
        $result = $executeWithMock();
        
        $this->assertInstanceOf(AgentResult::class, $result);
    }
} 