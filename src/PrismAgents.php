<?php

namespace Grpaiva\PrismAgents;

use Closure;
use Prism\Prism\Enums\Provider;

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
     * @param Trace|string|null $trace Optional trace or trace name
     * @return AgentResultBuilder
     */
    public static function run(Agent $agent, $input, Trace|string|null $trace = null): AgentResultBuilder
    {
        $runner = new Runner();
        
        // Set trace if provided
        if ($trace !== null) {
            if (is_string($trace)) {
                $trace = Trace::as($trace);
            }
            $runner->withTrace($trace);
        }
        
        $result = $runner->runAgent($agent, $input);
        return new AgentResultBuilder($result);
    }

    /**
     * Process a Prism response directly
     *
     * @param mixed $prismResponse The raw Prism response
     * @param Agent|null $agent The agent that generated the response (optional)
     * @param mixed $input The input that was given to the agent (optional)
     * @param Trace|string|null $trace Optional trace or trace name
     * @return AgentResultBuilder
     */
    public static function processPrismResponse($prismResponse, ?Agent $agent = null, $input = null, Trace|string|null $trace = null): AgentResultBuilder
    {
        // Convert to array if it's a JSON string
        if (is_string($prismResponse) && (str_starts_with($prismResponse, '{') || str_starts_with($prismResponse, '['))) {
            $decoded = json_decode($prismResponse, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $prismResponse = $decoded;
            }
        }
        
        // Create an AgentResult from the Prism response
        $result = AgentResult::create($agent, $input);
        $result->loadFromPrismResponse($prismResponse);
        
        // Add trace if provided
        $builder = new AgentResultBuilder($result);
        if ($trace !== null) {
            $builder->withTrace($trace);
        }
        
        return $builder;
    }

    /**
     * Create a result from raw data
     * 
     * @param mixed $output The output text or data
     * @param Agent|null $agent The agent that generated the output (optional)
     * @param mixed $input The input that was given to the agent (optional)
     * @param array $metadata Additional metadata (optional)
     * @return AgentResultBuilder
     */
    public static function createResult($output, ?Agent $agent = null, $input = null, array $metadata = []): AgentResultBuilder
    {
        $result = AgentResult::create($agent, $input);
        
        if (is_string($output)) {
            $result->setOutput($output);
        } elseif (is_array($output) && isset($output['text'])) {
            $result->setOutput($output['text']);
            // Add the rest as metadata
            $outputMetadata = $output;
            unset($outputMetadata['text']);
            $metadata = array_merge($outputMetadata, $metadata);
        }
        
        if (!empty($metadata)) {
            $result->setMetadata($metadata);
        }
        
        return new AgentResultBuilder($result);
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
