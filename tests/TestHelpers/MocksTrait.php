<?php

namespace Grpaiva\PrismAgents\Tests\TestHelpers;

use Prism\Prism\Tool;
use Mockery as m;

trait MocksTrait
{
    /**
     * Create a mock Tool
     */
    protected function mockTool(string $name = 'mock_tool', string $description = 'Mock tool description'): Tool
    {
        $tool = m::mock(Tool::class);
        $tool->shouldReceive('name')->andReturn($name);
        $tool->shouldReceive('description')->andReturn($description);
        $tool->shouldReceive('__invoke')->andReturn('Mock tool result');
        $tool->shouldReceive('parameters')->andReturn([]);
        $tool->shouldReceive('requiredParameters')->andReturn([]);
        
        return $tool;
    }
    
    /**
     * Create a mockery alias
     * 
     * This is different from the Orchestra\Testbench\TestCase::partialMock
     */
    protected function createMockeryAlias(string $class)
    {
        return m::mock('alias:' . $class);
    }
} 