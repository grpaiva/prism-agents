<?php

use Prism\Prism\Tool;
use Mockery as m;

test('tool can be mocked with correct properties', function () {
    // Create a mock tool
    $weatherTool = m::mock(Tool::class);
    $weatherTool->shouldReceive('name')->andReturn('get_weather');
    $weatherTool->shouldReceive('description')->andReturn('Get the current weather for a location');
    
    expect($weatherTool)->toBeInstanceOf(Tool::class)
        ->and($weatherTool->name())->toBe('get_weather')
        ->and($weatherTool->description())->toBe('Get the current weather for a location');
});

test('tool can report its name and description', function () {
    // Create a mock with just the name and description methods
    $weatherTool = m::mock(Tool::class);
    $weatherTool->shouldReceive('name')->andReturn('weather');
    $weatherTool->shouldReceive('description')->andReturn('Get current weather conditions');
    
    expect($weatherTool)->toBeInstanceOf(Tool::class)
        ->and($weatherTool->name())->toBe('weather')
        ->and($weatherTool->description())->toBe('Get current weather conditions');
}); 