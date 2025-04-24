<?php

namespace Grpaiva\PrismAgents;

use Closure;
use Grpaiva\PrismAgents\Tracing\Tracer;
use Prism\Prism\Enums\Provider;
use Throwable;

class PrismAgents
{
    /**
     * Create a new agent
     *
     * @param string $name
     * @param string $instructions
     * @param array $config
     * @return Agent
     */
    public static function agent(string $name, string $instructions, array $config = []): Agent
    {
        $agent = Agent::as($name)->withInstructions($instructions);
        
        // Apply optional configuration
        if (isset($config['handoffDescription'])) {
            $agent->withHandoffDescription($config['handoffDescription']);
        }
        
        if (isset($config['tools'])) {
            $agent->withTools($config['tools']);
        }
        
        if (isset($config['provider']) && isset($config['model'])) {
            $provider = is_string($config['provider']) 
                ? self::mapProvider($config['provider']) 
                : $config['provider'];
            $agent->using($provider, $config['model']);
        }
        
        if (isset($config['inputGuardrails'])) {
            $agent->withInputGuardrails($config['inputGuardrails']);
        }
        
        if (isset($config['maxSteps'])) {
            $agent->steps($config['maxSteps']);
        }
        
        return $agent;
    }

    /**
     * Create a new tool from a closure
     *
     * @param string $name
     * @param string $description
     * @param Closure $handler
     * @param array|null $parameters
     * @return Tool
     */
    public static function tool(string $name, string $description, Closure $handler, ?array $parameters = null): Tool
    {
        $tool = \Prism\Prism\Facades\Tool::as($name)->for($description)->using($handler);
        
        // If parameters are provided, add them to the tool
        if ($parameters !== null && isset($parameters['properties'])) {
            foreach ($parameters['properties'] as $paramName => $paramConfig) {
                $type = $paramConfig['type'] ?? 'string';
                $paramDescription = $paramConfig['description'] ?? '';
                $required = in_array($paramName, $parameters['required'] ?? []);
                
                switch ($type) {
                    case 'string':
                        $tool->withStringParameter($paramName, $paramDescription, $required);
                        break;
                    case 'number':
                    case 'integer':
                        $tool->withNumberParameter($paramName, $paramDescription, $required);
                        break;
                    case 'boolean':
                        $tool->withBooleanParameter($paramName, $paramDescription, $required);
                        break;
                    case 'array':
                        $itemType = $paramConfig['items'] ?? ['type' => 'string'];
                        // Convert to Prism schema
                        $itemSchema = new \Prism\Prism\Schema\StringSchema('item', 'Array item');
                        $tool->withArrayParameter($paramName, $paramDescription, $itemSchema, $required);
                        break;
                    case 'object':
                        // For objects, we need to recursively build the schema
                        // This is a simplified version
                        $tool->withObjectParameter($paramName, $paramDescription, [], [], $required);
                        break;
                }
            }
        }
        
        return $tool;
    }

    /**
     * Run an agent with the given input
     *
     * @param Agent $agent
     * @param string|array $input
     * @param Tracer|string|null $tracerOrId Optional Tracer instance or execution ID/workflow name string.
     * @return AgentResultBuilder
     */
    public static function run(Agent $agent, $input, Tracer|string|null $tracerOrId = null): AgentResultBuilder
    {
        $tracer = null;
        $shouldEndExecution = false;

        // Initialize or set tracer
        if ($tracerOrId instanceof Tracer) {
            $tracer = $tracerOrId;
        } elseif (is_string($tracerOrId)) {
            // Assume the string is a workflow name / execution ID
            $tracer = new Tracer(null, $tracerOrId); 
            $shouldEndExecution = true; // If we create it here, we should end it here
        } else {
            // No tracer provided, create a default one
            $tracer = new Tracer(null, $agent->getName()); 
            $shouldEndExecution = true;
        }
        
        $runner = new Runner($tracer); // Pass tracer to Runner
        
        try {
            $result = $runner->runAgent($agent, $input);
            // Ensure the result has the execution ID if tracing is enabled
            if ($tracer && $tracer->isEnabled() && $tracer->getExecutionId()) {
                $result->setExecutionId($tracer->getExecutionId());
            }
            return new AgentResultBuilder($result);
        } catch (Throwable $e) {
            // If an error occurred during runAgent, the runner's span is ended with error.
            // We still need to end the overall execution here.
            if ($shouldEndExecution && $tracer) {
                $tracer->endExecution('failed', $e);
            }
            throw $e; // Re-throw exception
        } finally {
            // End the execution trace if it was started within this run method
            if ($shouldEndExecution && $tracer) {
                 // If runAgent completed without error, endExecution status defaults to 'completed'
                 // If runAgent threw an error, endExecution was already called in catch block
                 if (!isset($e)) { // Only end normally if no exception was caught
                     $tracer->endExecution(); 
                 }
            }
        }
    }

    /**
     * Create a new trace
     *
     * @param string|null $traceId
     * @return Trace
     */
    public static function trace(?string $traceId = null): Trace
    {
        return Trace::as($traceId);
    }

    /**
     * Create a new context
     *
     * @param array $data
     * @param AgentContext|null $parent
     * @return AgentContext
     */
    public static function context(array $data = [], ?AgentContext $parent = null): AgentContext
    {
        $context = AgentContext::as();
        
        if (!empty($data)) {
            $context->withData($data);
        }
        
        if ($parent) {
            $context->withParent($parent);
        }
        
        return $context;
    }

    /**
     * Map a provider string to Prism Provider enum
     *
     * @param string $provider
     * @return Provider
     */
    public static function mapProvider(string $provider): Provider
    {
        $map = [
            'openai' => Provider::OpenAI,
            'anthropic' => Provider::Anthropic,
            // Add more mappings as needed
        ];
        
        return $map[strtolower($provider)] ?? Provider::OpenAI;
    }
}
