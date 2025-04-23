<?php

use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\PrismAgents;
use Grpaiva\PrismAgents\Tool;
use Prism\Prism\Enums\Provider;

require_once __DIR__ . '/../vendor/autoload.php';

// Create an agent with tools
$agent = Agent::as('assistant')
    ->withInstructions('You are a helpful assistant that answers user questions. Use the provided tools when appropriate.')
    ->using(Provider::OpenAI, 'gpt-4o')
    ->steps(5)
    ->withTools([
        Tool::as('weather-tool')
            ->for('A tool to get weather information for a location')
            ->using(function ($args) {
                // For demonstration, return a static response
                $location = $args['location'] ?? 'unknown location';
                return "The weather in {$location} is currently sunny with a temperature of 72°F (22°C).";
            })
            ->withStringParameter('location', 'The location to get weather for', true),
            
        Tool::as('time-tool')
            ->for('A tool to get the current time for a location')
            ->using(function ($args) {
                // For demonstration, return current server time
                $location = $args['location'] ?? 'local';
                $time = date('h:i A');
                return "The current time in {$location} is approximately {$time}.";
            })
            ->withStringParameter('location', 'The location to get the time for', true)
    ])
    ->withInputGuardrails([
        'input' => 'string'
    ]);

// Run the agent with a query
$result = PrismAgents::run(
    $agent,
    "What's the weather like in San Francisco? Also, what time is it in Tokyo?"
);

// Output the result
echo "Agent response:\n\n";
echo $result->getOutput() . "\n\n";

// Check if any tools were called and display the results
if (count($result->getToolResults()) > 0) {
    echo "Tool calls made:\n";
    foreach ($result->getToolResults() as $index => $toolResult) {
        echo ($index + 1) . ". Tool: " . $toolResult['toolName'] . "\n";
        echo "   Result: " . $toolResult['result'] . "\n";
    }
}

// Display the number of steps taken
echo "\nSteps taken: " . count($result->getSteps()) . "\n"; 