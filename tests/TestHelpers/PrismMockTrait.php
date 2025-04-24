<?php

namespace Grpaiva\PrismAgents\Tests\TestHelpers;

use Mockery as m;
use Grpaiva\PrismAgents\PrismAgents;
use Grpaiva\PrismAgents\AgentResult;

/**
 * Trait for mocking Prism and PrismAgents classes in tests
 */
trait PrismMockTrait
{
    protected function mockPrismManager()
    {
        // Create a mock OpenAI provider
        $openaiProvider = m::mock('Prism\Prism\Providers\OpenAI');
        $openaiProvider->shouldReceive('run')->andReturn([
            'output' => 'This is a mocked response from the AI.',
            'model' => 'gpt-4o',
            'usage' => [
                'input_tokens' => 50,
                'output_tokens' => 20,
                'total_tokens' => 70
            ]
        ]);
        
        // Create a mock Prism manager that returns the mocked provider
        $prismManager = m::mock('Prism\Prism\PrismManager');
        $prismManager->shouldReceive('provider')->andReturn($openaiProvider);
        
        // Bind the mocked manager to the container
        app()->instance('Prism\Prism\PrismManager', $prismManager);
        
        return $prismManager;
    }
    
    protected function mockPrismAgents()
    {
        // This will mock the PrismAgents class to avoid actually running the agent
        $prismAgents = m::mock('Grpaiva\PrismAgents\PrismAgents');
        $prismAgents->shouldReceive('run')->andReturn(
            m::mock('Grpaiva\PrismAgents\AgentResult')
                ->shouldReceive('getOutput')->andReturn('Mocked agent response')
                ->shouldReceive('getTokensUsed')->andReturn(100)
                ->shouldReceive('getToolResults')->andReturn([])
                ->getMock()
        );
        
        app()->instance('Grpaiva\PrismAgents\PrismAgents', $prismAgents);
        
        return $prismAgents;
    }
    
    /**
     * Helper to create a mock PrismAgents with configurable behavior
     */
    protected function createMockPrismAgents()
    {
        $mock = m::mock(PrismAgents::class);
        
        // Set up default behavior for run
        $agentResult = m::mock(AgentResult::class);
        $agentResult->shouldReceive('getOutput')->andReturn('Mocked agent response');
        $agentResult->shouldReceive('getTokensUsed')->andReturn(70);
        $agentResult->shouldReceive('getToolResults')->andReturn([]);
        $agentResult->shouldReceive('withTrace')->andReturnSelf();
        
        $mock->shouldReceive('run')->andReturn($agentResult);
        
        return $mock;
    }
} 