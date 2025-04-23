<?php

namespace Grpaiva\PrismAgents\Tests;

use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\AgentContext;
use Grpaiva\PrismAgents\Runner;
use Grpaiva\PrismAgents\Tool;
use Grpaiva\PrismAgents\Trace;
use Grpaiva\PrismAgents\Guardrail;
use Grpaiva\PrismAgents\GuardrailResult;
use Grpaiva\PrismAgents\GuardrailException;
use Mockery\MockInterface;
use Mockery\LegacyMockInterface;
use Prism\Prism\Prism;
use Prism\Prism\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

class RunnerTest extends TestCase
{
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_can_run_a_simple_agent()
    {
        // Mock the Prism facade and the asText method chain
        $this->mockPrismResponse('This is a test response');
        
        $agent = new Agent('test-agent', 'You are a test agent');
        $runner = new Runner();
        
        $result = $runner->runAgent($agent, 'test input');
        
        $this->assertEquals('This is a test response', $result->getFinalOutput());
    }
    
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_can_run_an_agent_with_tools()
    {
        // Create a mock response with tool results
        $responseData = (object)[
            'text' => 'Tool was used successfully',
            'toolResults' => [
                (object)[
                    'toolName' => 'test-tool',
                    'result' => 'tool result data'
                ]
            ],
            'steps' => [
                (object)[
                    'type' => 'tool_call',
                    'name' => 'test-tool',
                ]
            ]
        ];
        
        $this->mockPrismResponse($responseData);
        
        $tool = new Tool('test-tool', 'A test tool', function ($args) {
            return 'tool result';
        });
        
        $agent = new Agent('test-agent', 'You are a test agent', [
            'tools' => [$tool]
        ]);
        
        $runner = new Runner();
        $result = $runner->runAgent($agent, 'use the test tool');
        
        $this->assertEquals('Tool was used successfully', $result->getFinalOutput());
        $this->assertCount(1, $result->getToolResults());
        $this->assertEquals('test-tool', $result->getToolResults()[0]['toolName']);
        $this->assertEquals('tool result data', $result->getToolResults()[0]['result']);
    }
    
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_throws_exception_when_guardrail_fails()
    {
        $agent = new Agent('test-agent', 'You are a test agent');
        
        // Add a failing guardrail
        $guardrail = \Mockery::mock(Guardrail::class);
        $guardrail->shouldReceive('check')
            ->once()
            ->andReturn(GuardrailResult::fail('Guardrail check failed', 400));
        
        $agent->addGuardrail($guardrail);
        
        $runner = new Runner();
        
        $this->expectException(GuardrailException::class);
        $this->expectExceptionMessage('Guardrail check failed');
        
        $runner->runAgent($agent, 'test input');
    }
    
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_uses_tracing_for_spans()
    {
        $this->mockPrismResponse('Test response');
        
        $trace = \Mockery::mock(Trace::class);
        $trace->shouldReceive('startSpan')
            ->once()
            ->with('test-agent', 'agent_run')
            ->andReturn('test-span-id');
        
        $trace->shouldReceive('endSpan')
            ->once()
            ->with('test-span-id', \Mockery::type('array'))
            ->andReturnSelf();
        
        $agent = new Agent('test-agent', 'You are a test agent');
        $runner = new Runner($trace);
        
        $result = $runner->runAgent($agent, 'test input');
        
        $this->assertEquals('Test response', $result->getFinalOutput());
    }
    
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function it_can_run_an_agent_with_array_input()
    {
        $this->mockPrismResponse('Response to conversation');
        
        $agent = new Agent('test-agent', 'You are a test agent');
        $runner = new Runner();
        
        $input = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
            ['role' => 'user', 'content' => 'How are you?']
        ];
        
        $result = $runner->runAgent($agent, $input);
        
        $this->assertEquals('Response to conversation', $result->getFinalOutput());
    }
    
    /**
     * Helper method to mock the Prism facade response
     */
    private function mockPrismResponse($responseData)
    {
        // Create a mock response object
        if (is_string($responseData)) {
            $response = Mockery::mock(Response::class);
            $response->text = $responseData;
            $response->toolResults = [];
            $response->steps = [];
        } else {
            $response = Mockery::mock(Response::class);
            $response->text = $responseData->text;
            $response->toolResults = $responseData->toolResults ?? [];
            $response->steps = $responseData->steps ?? [];
        }
        
        // Create a fluent mock that handles the method chain
        $fluent = Mockery::mock();
        $fluent->shouldReceive('using')->andReturnSelf();
        $fluent->shouldReceive('withSystemPrompt')->andReturnSelf();
        $fluent->shouldReceive('withPrompt')->andReturnSelf();
        $fluent->shouldReceive('withMessage')->andReturnSelf();
        $fluent->shouldReceive('withTools')->andReturnSelf();
        $fluent->shouldReceive('asText')->andReturn($response);
        
        // Mock the Prism facade
        $prismMock = Mockery::mock('alias:Prism\Prism\Prism');
        $prismMock->shouldReceive('text')->andReturn($fluent);
    }
    
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
} 