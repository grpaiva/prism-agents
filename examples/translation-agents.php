<?php

use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\PrismAgents;
use Prism\Prism\Enums\Provider;

require_once __DIR__ . '/../vendor/autoload.php';

// Create specialized translation agents
$spanishAgent = Agent::as('spanish_agent')
    ->withInstructions('You translate the user\'s message to Spanish')
    ->using(Provider::Anthropic, 'claude-3.5-haiku')
    ->withHandoffDescription('An english to spanish translator');

$frenchAgent = Agent::as('french_agent')
    ->withInstructions('You translate the user\'s message to French')
    ->using(Provider::Anthropic, 'claude-3.5-haiku')
    ->withHandoffDescription('An english to french translator');

$italianAgent = Agent::as('italian_agent')
    ->withInstructions('You translate the user\'s message to Italian')
    ->using(Provider::Anthropic, 'claude-3.5-haiku')
    ->withHandoffDescription('An english to italian translator');

// Create an orchestrator agent that will use the specialized agents as tools
$orchestratorAgent = Agent::as('orchestrator_agent')
    ->withInstructions("You are a translation agent. You use the tools given to you to translate. If asked for multiple translations, you call the relevant tools in order. You never translate on your own, you always use the provided tools.")
    ->using(Provider::Anthropic, 'claude-3.5-sonnet')
    ->withTools([
        $spanishAgent->asTool(),
        $frenchAgent->asTool(),
        $italianAgent->asTool()
    ])
    ->withInputGuardrails([
        'input' => 'string'
    ]);

// Create a synthesizer agent to process the results
$synthesizerAgent = Agent::as('synthesizer_agent')
    ->withInstructions("You inspect translations, correct them if needed, and produce a final concatenated response.")
    ->using(Provider::Anthropic, 'claude-3.5-sonnet');

// Run the orchestrator with a translation request (including trace by name)
$orchestratorResult = PrismAgents::run(
    $orchestratorAgent,
    "Translate 'Hello, how are you?' to Spanish."
)->withTrace('translation_process');

// Output the result from the orchestrator
echo "Orchestrator result: " . $orchestratorResult->getOutput() . PHP_EOL;

// Process the orchestrator result with the synthesizer, adding to the same trace
$synthesizerResult = PrismAgents::run(
    $synthesizerAgent,
    $orchestratorResult
)->withTrace('translation_process');

// Output the final synthesized result
echo "Synthesizer result: " . $synthesizerResult->getOutput() . PHP_EOL;

// Example of checking trace information (need to retrieve the trace first)
$trace = \Grpaiva\PrismAgents\Trace::retrieve('translation_process');
if ($trace) {
    echo "Number of spans in trace: " . count($trace->getSpans()) . PHP_EOL;
} 