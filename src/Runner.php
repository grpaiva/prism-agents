<?php

namespace Grpaiva\PrismAgents;

use Grpaiva\PrismAgents\Exceptions\GuardrailException;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Text\Response;
use Illuminate\Support\Facades\Log;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class Runner
{
    /**
     * Default system message for all agents
     */
    protected const DEFAULT_SYSTEM_MESSAGE = "You are part of a multi-agent system called the Agents SDK, designed to make agent coordination and execution easy. Agents uses two primary abstraction: Agents and Handoffs. An agent encompasses instructions and tools and can hand off a conversation to another agent when appropriate. Handoffs are achieved by calling a handoff function, generally named transfer_to_<agent_name>. Transfers between agents are handled seamlessly in the background; do not mention or draw attention to these transfers in your conversation with the user.";

    /**
     * @var Trace|null
     */
    protected ?Trace $trace = null;

    /**
     * @var int|null
     */
    protected ?int $maxSteps = null;

    /**
     * @var array
     */
    protected array $clientOptions = [];

    /**
     * Create a new Runner instance
     */
    public function __construct()
    {
        $this->trace = Trace::as('agent_runner');
    }

    /**
     * Set the trace for this runner
     * 
     * @param Trace|string $trace Trace object or trace name
     * @return $this
     */
    public function withTrace(Trace|string $trace): self
    {
        if (is_string($trace)) {
            $trace = Trace::as($trace);
        }
        
        $this->trace = $trace;
        return $this;
    }

    /**
     * Set the client options for the Prism client
     *
     * @param array $options
     * @return $this
     */
    public function withClientOptions(array $options): self
    {
        $this->clientOptions = $options;
        return $this;
    }

    /**
     * Set the maximum number of steps
     * 
     * @param int $steps
     * @return $this
     */
    public function withMaxSteps(int $steps): self
    {
        $this->maxSteps = $steps;
        return $this;
    }

    /**
     * Run an agent with the given input
     *
     * @param Agent $agent
     * @param string|array $input
     * @param AgentContext|null $context
     * @return AgentResult
     */
    public function runAgent(Agent $agent, $input, ?AgentContext $context = null): AgentResult
    {
        // Create a context if none is provided
        $context = $context ?? AgentContext::as('runner_context');

        // Start tracing
        $spanId = $this->trace->startSpan($agent->getName(), 'agent_run');
        
        try {
            // Check guardrails before execution
            $this->checkGuardrails($agent, $input, $context);
            
            // Prepare tools
            $tools = $agent->getTools();
            $prismTools = [];
            
            // Convert tools to Prism Tool objects if necessary
            foreach ($tools as $tool) {
                if ($tool instanceof \Prism\Prism\Tool) {
                    $prismTools[] = $tool;
                } elseif ($tool instanceof \Grpaiva\PrismAgents\Tool) {
                    $prismTools[] = $tool->getPrismTool();
                }
            }
            
            // Build a Prism request using the agent's configuration
            $combinedSystemPrompt = self::DEFAULT_SYSTEM_MESSAGE . "\n\n" . $agent->getInstructions();
            $prismRequest = Prism::text()
                ->withSystemPrompt($combinedSystemPrompt);
            
            // Set provider and model if specified in the agent
            if ($agent->getProvider() && $agent->getModel()) {
                $prismRequest->using(
                    $agent->getProvider(), 
                    $agent->getModel()
                );
            }

            // Set client options if specified
            if (!empty($this->clientOptions)) {
                $prismRequest->withClientOptions($this->clientOptions);
            }

            // Set maximum steps if specified
            $maxSteps = $agent->getMaxSteps() ?? $this->maxSteps;
            if ($maxSteps) {
                $prismRequest->withMaxSteps($maxSteps);
            }
            
            // Add tools if there are any
            if (!empty($prismTools)) {
                $prismRequest->withTools($prismTools);
            }
            
            // Handle different input types
            if (is_string($input)) {
                $prismRequest->withPrompt($input);
            } elseif ($input instanceof AgentResult) {
                // If input is an AgentResult, use its output as the prompt
                $prismRequest->withPrompt($input->getOutput());
                // Add context from previous result if useful
                if ($input->getAgent()) {
                    $context->set('previous_agent', $input->getAgent()->getName());
                }
            } elseif (is_array($input)) {
                // Assuming input is a conversation history array
                foreach ($input as $message) {
                    if (isset($message['role']) && isset($message['content'])) {
                        $messages[] = match ($message['role']) {
                            'system' => new SystemMessage($message['content']),
                            'assistant' => new AssistantMessage($message['content']),
                            default => new UserMessage($message['content']),
                        };

                        // Add messages to the request
                        $prismRequest->withMessages($messages);
                    } else {
                        throw new \InvalidArgumentException('Invalid message format in input array.');
                    }
                }
            }
            
            // Execute the request
            $response = $prismRequest->asText();
            
            // Process the response
            $result = $this->processResponse($response, $agent, $input, $context);
            
            // Add system messages to the result metadata for tracing
            $metadata = $result->getMetadata() ?? [];
            $metadata['system_message'] = [
                'default' => self::DEFAULT_SYSTEM_MESSAGE,
                'agent_instructions' => $agent->getInstructions(),
                'combined' => $combinedSystemPrompt
            ];
            $result->setMetadata($metadata);
            
            // Complete the trace span
            $this->trace->endSpan($spanId, [
                'status' => 'success',
                'result' => $result->toArray(),
            ]);
            
            return $result;
        } catch (\Exception $e) {
            // Log and trace the error
            Log::error('Error in agent run: ' . $e->getMessage(), [
                'agent' => $agent->getName(),
                'exception' => $e,
            ]);
            
            // End span with error
            $this->trace->endSpan($spanId, [
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Process the response from the LLM
     *
     * @param Response $response Prism Text Response
     * @param Agent $agent
     * @param string|array|AgentResult $input
     * @param AgentContext $context
     * @return AgentResult
     */
    protected function processResponse(Response $response, Agent $agent, $input, AgentContext $context): AgentResult
    {
        $result = AgentResult::create($agent, $input);
        
        // Set the final text output if any
        if ($response->text) {
            $result->setOutput($response->text);
        }
        
        // Process tool calls if any
        if (!empty($response->toolResults)) {
            foreach ($response->toolResults as $toolResult) {
                $result->addToolResult($toolResult->toolName, $toolResult->result);
            }
        }
        
        // Add steps
        if ($response->steps->isNotEmpty()) {
            foreach ($response->steps as $step) {
                $result->addStep([
                    'text' => $step->text,
                    'finish_reason' => $this->enumToString($step->finishReason),
                    'tool_calls' => $step->toolCalls,
                    'tool_results' => $step->toolResults,
                    'additional_content' => $step->additionalContent,
                ]);
            }
        }
        
        // Add additional metadata
        $usage = $this->extractUsageData($response->usage);
        $metadata = [
            'finish_reason' => $this->enumToString($response->finishReason),
            'usage' => $usage,
        ];
        
        // Safely extract model information
        if (isset($response->meta) && is_object($response->meta)) {
            if (property_exists($response->meta, 'model')) {
                $metadata['model'] = $response->meta->model;
            }
            
            // Extract provider information safely
            $provider = $this->extractProviderFromMeta($response->meta);
            if ($provider) {
                $metadata['provider'] = $provider;
            }
        }
        
        $result->setMetadata($metadata);
        
        return $result;
    }

    /**
     * Safely extract usage data from the response usage object
     *
     * @param mixed $usage
     * @return array
     */
    protected function extractUsageData($usage): array
    {
        $data = [];
        
        // Check for various possible property names
        $data['prompt_tokens'] = $this->getPropertyValue($usage, ['promptTokens', 'prompt_tokens', 'input_tokens']);
        $data['completion_tokens'] = $this->getPropertyValue($usage, ['completionTokens', 'completion_tokens', 'output_tokens']);
        
        // If totalTokens is missing, calculate it if we have both prompt and completion tokens
        $data['total_tokens'] = $this->getPropertyValue($usage, ['totalTokens', 'total_tokens', 'total']);
        
        if (!$data['total_tokens'] && isset($data['prompt_tokens']) && isset($data['completion_tokens'])) {
            $data['total_tokens'] = $data['prompt_tokens'] + $data['completion_tokens'];
        }
        
        return $data;
    }
    
    /**
     * Try to get a property value from an object by checking multiple possible property names
     *
     * @param object $object
     * @param array $possibleNames
     * @return mixed|null
     */
    protected function getPropertyValue($object, array $possibleNames)
    {
        if (!is_object($object)) {
            return null;
        }
        
        foreach ($possibleNames as $name) {
            if (property_exists($object, $name)) {
                return $object->$name;
            }
        }
        
        return null;
    }

    /**
     * Convert a pure enum to a string representation
     *
     * @param mixed $enum
     * @return string
     */
    protected function enumToString($enum): string
    {
        if (is_object($enum) && enum_exists(get_class($enum))) {
            return strtolower($enum->name);
        }
        
        return (string) $enum;
    }

    /**
     * Check input guardrails before executing the agent
     *
     * @param Agent $agent
     * @param string|array $input
     * @param AgentContext $context
     * @throws GuardrailException
     */
    protected function checkGuardrails(Agent $agent, $input, AgentContext $context): void
    {
        $guardrails = $agent->getInputGuardrails();
        
        foreach ($guardrails as $guardrail) {
            if ($guardrail instanceof Guardrail) {
                $result = $guardrail->check($input, $context);
                
                if (!$result->passes()) {
                    throw new GuardrailException(
                        $result->getMessage() ?? 'Input guardrail check failed',
                        $result->getCode() ?? 400
                    );
                }
            } elseif (is_callable($guardrail)) {
                $result = call_user_func($guardrail, $input, $context);
                
                if ($result === false || (is_object($result) && method_exists($result, 'passes') && !$result->passes())) {
                    throw new GuardrailException('Input guardrail check failed', 400);
                }
            }
        }
    }

    /**
     * Map Prism's provider name to Provider enum
     *
     * @param string $provider
     * @return Provider
     */
    protected function mapProviderName(string $provider): Provider
    {
        $map = [
            'openai' => Provider::OpenAI,
            'anthropic' => Provider::Anthropic,
            // Add more mappings as needed
        ];
        
        return $map[strtolower($provider)] ?? Provider::OpenAI;
    }

    /**
     * Safely extract provider information from Meta object
     *
     * @param object $meta
     * @return string|null
     */
    protected function extractProviderFromMeta($meta): ?string
    {
        // Check if provider exists directly as a property
        if (property_exists($meta, 'provider')) {
            $provider = $meta->provider;
            
            // If provider is an enum, get its value or name
            if (is_object($provider) && enum_exists(get_class($provider))) {
                return property_exists($provider, 'value') ? $provider->value : strtolower($provider->name);
            }
            
            return (string) $provider;
        }
        
        // Try to find provider in other common property names
        foreach (['providerName', 'provider_name', 'llm_provider'] as $propertyName) {
            if (property_exists($meta, $propertyName)) {
                return (string) $meta->$propertyName;
            }
        }
        
        return null;
    }

    /**
     * Convert tool to a definition array compatible with Prism
     *
     * @param mixed $tool
     * @return array
     */
    protected function convertToolToDefinition($tool): array
    {
        // If tool has toDefinition method, use it
        if (method_exists($tool, 'toDefinition')) {
            return $tool->toDefinition();
        }
        
        // If it's a vendor Tool object, convert it manually
        if ($tool instanceof \Prism\Prism\Tool) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $tool->parameters(),
                        'required' => $tool->requiredParameters(),
                    ],
                ],
            ];
        }
        
        // For other types, try to cast to array if possible
        if (method_exists($tool, 'toArray')) {
            return $tool->toArray();
        }
        
        // Last resort, return as is if it's already an array
        if (is_array($tool)) {
            return $tool;
        }
        
        throw new \InvalidArgumentException('Tool type not supported: ' . (is_object($tool) ? get_class($tool) : gettype($tool)));
    }

    /**
     * Static helper method to run an agent
     *
     * @param Agent $agent
     * @param string|array $input
     * @param AgentContext|null $context
     * @return AgentResult
     */
    public static function run(Agent $agent, $input, ?AgentContext $context = null): AgentResult
    {
        $runner = new self();
        return $runner->runAgent($agent, $input, $context);
    }
} 