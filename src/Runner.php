<?php

namespace Grpaiva\PrismAgents;

use Grpaiva\PrismAgents\Exceptions\GuardrailException;
use Grpaiva\PrismAgents\Tracing\Tracer;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Text\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Runner
{
    /**
     * Default system message for all agents
     */
    protected const DEFAULT_SYSTEM_MESSAGE = "You are part of a multi-agent system called the Agents SDK, designed to make agent coordination and execution easy. Agents uses two primary abstraction: Agents and Handoffs. An agent encompasses instructions and tools and can hand off a conversation to another agent when appropriate. Handoffs are achieved by calling a handoff function, generally named transfer_to_<agent_name>. Transfers between agents are handled seamlessly in the background; do not mention or draw attention to these transfers in your conversation with the user.";

    /**
     * @var Tracer|null
     */
    protected ?Tracer $tracer = null;

    /**
     * @var int|null
     */
    protected ?int $maxSteps = null;

    /**
     * Create a new Runner instance.
     * If no tracer is provided, a new one will be created for this run.
     */
    public function __construct(?Tracer $tracer = null)
    {
        $this->tracer = $tracer;
    }

    /**
     * Set the tracer for this runner.
     * 
     * @param Tracer $tracer
     * @return $this
     */
    public function withTracer(Tracer $tracer): self
    {
        $this->tracer = $tracer;
        return $this;
    }

    /**
     * Set the maximum number of steps
     * 
     * @param int $steps
     * @return $this
     */
    public function steps(int $steps): self
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
        // Ensure a tracer is available, create one if necessary
        if (!$this->tracer) {
            // Use agent name for workflow if creating tracer here
            $this->tracer = new Tracer(null, $agent->getName()); 
        }

        // Create a context if none is provided
        $context = $context ?? AgentContext::as('runner_context');

        // Start tracing for this specific agent execution span
        $agentSpan = $this->tracer?->startSpan(
            $agent->getName(), 
            'agent_execution', // Use 'agent_execution' for the main agent run
            ['input' => $input] // Initial span data
        );
        
        try {
            // Check guardrails before execution
            $this->checkGuardrails($agent, $input, $context);
            
            // Prepare tools
            $tools = $agent->getTools();
            $prismTools = [];
            $agentToolNames = []; // Keep track of tools that are agents (for handoff detection)
            
            // Convert tools to Prism Tool objects if necessary
            foreach ($tools as $tool) {
                // --- Handle Agent as Tool --- 
                if ($tool instanceof Agent) {
                    $agentInstance = $tool; // Keep reference to the agent
                    $toolName = $agentInstance->getName();
                    $toolDescription = $agentInstance->getHandoffDescription() ?? "Agent: {$agentInstance->getName()}";
                    $currentTracer = $this->tracer; // Capture current tracer

                    $prismAgentTool = \Prism\Prism\Facades\Tool::as($toolName)
                        ->for($toolDescription)
                        ->withStringParameter('input', 'Input for the agent', true) // Assume agent tools take a single string input
                        ->using(function (string $input) use ($agentInstance, $currentTracer) {
                            // Execute the sub-agent using the *captured* tracer
                            $subRunner = new Runner($currentTracer);
                            // We might need to pass more context here eventually
                            $result = $subRunner->runAgent($agentInstance, $input);
                            // Return the output, or maybe the full AgentResult?
                            // Returning just output for now based on previous logic.
                            return $result->getOutput(); 
                        });
                        
                    $prismTools[] = $prismAgentTool;
                    $agentToolNames[] = $toolName; // Mark this as an agent tool name
                } 
                // --- Handle Standard Prism Tool --- 
                elseif ($tool instanceof \Prism\Prism\Tool) {
                    $prismTools[] = $tool;
                    // Heuristic: Assume direct Prism tools aren't agents?
                } 
                // --- Handle internal Tool wrapper (potentially deprecated?) ---
                elseif ($tool instanceof \Grpaiva\PrismAgents\Tool) {
                    // This path might need review - is Grpaiva\PrismAgents\Tool still needed?
                    $prismTools[] = $tool->getPrismTool(); 
                    $toolDefinition = $this->convertToolToDefinition($tool);
                    if ($tool->isAgentTool()) { 
                        $agentToolNames[] = $toolDefinition['name'] ?? null;
                    }
                }
                // --- Handle other potential tool types (e.g., Closures directly?) ---
                 else {
                    // Maybe convert closures or other definitions here if supported
                    // For now, let's assume only Agent or Prism\Tool are primary inputs
                 }
            }
            $agentToolNames = array_filter(array_unique($agentToolNames)); // Ensure uniqueness
            
            // Build a Prism request using the agent's configuration
            $combinedSystemPrompt = self::DEFAULT_SYSTEM_MESSAGE . "\n\n" . $agent->getInstructions();
            $prismRequest = Prism::text()
                ->withSystemPrompt($combinedSystemPrompt);
            
            // Start LLM step span (or maybe multiple spans if Prism has multiple steps?)
            // For now, assume one primary LLM interaction span
            $llmSpan = $this->tracer?->startSpan(
                'llm_request', 
                'llm_step', // A more specific type for the LLM interaction
                [
                    'provider' => $agent->getProvider()?->value, // Use ->value for Enum
                    'model' => $agent->getModel(),
                    'system_prompt' => $combinedSystemPrompt,
                    'tools_provided' => array_map(fn($t) => $this->convertToolToDefinition($t), $prismTools), // Use helper method
                ],
                $agentSpan?->id // Explicitly parent under agent span
            );

            // Set provider and model if specified in the agent
            if ($agent->getProvider() && $agent->getModel()) {
                $prismRequest->using(
                    $agent->getProvider(), 
                    $agent->getModel()
                );
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
                // Log prompt in LLM span data
                $this->tracer?->addEvent('Adding prompt', ['prompt' => $input], $llmSpan?->id);
                $prismRequest->withPrompt($input);
            } elseif ($input instanceof AgentResult) {
                // If input is an AgentResult, use its output as the prompt
                $this->tracer?->addEvent('Adding prompt from previous result', ['prompt' => $input->getOutput()], $llmSpan?->id);
                $prismRequest->withPrompt($input->getOutput());
                // Add context from previous result if useful
                if ($input->getAgent()) {
                    $context->set('previous_agent', $input->getAgent()->getName());
                }
            } elseif (is_array($input)) {
                // Assuming input is a conversation history array
                $this->tracer?->addEvent('Adding messages', ['messages' => $input], $llmSpan?->id);
                foreach ($input as $message) {
                    if (isset($message['role']) && isset($message['content'])) {
                        $prismRequest->withMessage($message['role'], $message['content']);
                    }
                }
            }
            
            $this->tracer?->addEvent('Sending request to LLM', [], $llmSpan?->id);
            // Execute the request
            $response = $prismRequest->asText();
            
            // Manually construct array from response properties for logging
            $responseDataForTrace = [
                'text' => $response->text,
                'finishReason' => $this->enumToString($response->finishReason),
                'toolCalls' => $response->toolCalls,
                'toolResults' => $response->toolResults,
                'steps' => $response->steps->toArray(), // Collection has toArray
                'usage' => $this->extractUsageData($response->usage),
                'meta' => $response->meta ? get_object_vars($response->meta) : null, // Convert meta object to array
            ];
            $this->tracer?->addEvent('Received response from LLM', ['response_raw' => $responseDataForTrace], $llmSpan?->id);

            // End the LLM span
            $this->tracer?->endSpan(
                $llmSpan?->id, 
                'success',
                [
                    // Use the manually constructed array here too
                    'response' => $responseDataForTrace, 
                    'usage' => $this->extractUsageData($response->usage) // Usage is already part of $responseDataForTrace, but keep for clarity?
                ]
            );

            // Process the response to create AgentResult and handle tool calls/steps
            $result = $this->processResponseAndTraceSteps($response, $agent, $input, $context, $agentSpan?->id, $agentToolNames);

            // Add system messages to the result metadata for tracing (and potential display)
            $metadata = $result->getMetadata() ?? [];
            $metadata['system_message'] = [
                'default' => self::DEFAULT_SYSTEM_MESSAGE,
                'agent_instructions' => $agent->getInstructions(),
                'combined' => $combinedSystemPrompt
            ];
            $result->setMetadata($metadata);
            
            // End the main agent execution span
            $this->tracer?->endSpan(
                $agentSpan?->id,
                'success',
                ['output' => $result->getOutput(), 'final_result' => $result->toArray()] // Add final output and result
            );
            
            return $result;
        } catch (\Exception $e) {
            // Log and trace the error
            Log::error('Error in agent run: ' . $e->getMessage(), [
                'agent' => $agent->getName(),
                'input' => $input,
                'exception' => $e,
            ]);
            
            // End the main agent span with error
            $this->tracer?->endSpan($agentSpan?->id, 'error', [], $e);
            
            throw $e; // Re-throw the exception
        }
    }

    /**
     * Process the response from the LLM, create AgentResult, and trace steps/tools.
     *
     * @param Response $response Prism Text Response
     * @param Agent $agent
     * @param string|array|AgentResult $input
     * @param AgentContext $context
     * @param string|null $parentSpanId The ID of the parent agent execution span.
     * @param array $agentToolNames Names of tools known to be other agents.
     * @return AgentResult
     */
    protected function processResponseAndTraceSteps(Response $response, Agent $agent, $input, AgentContext $context, ?string $parentSpanId, array $agentToolNames): AgentResult
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
        
        // Add steps and trace them
        if ($response->steps->isNotEmpty()) {
            $stepIndex = 0;
            foreach ($response->steps as $step) {
                $stepDataForAgentResult = [
                    'text' => $step->text,
                    'finish_reason' => $this->enumToString($step->finishReason),
                    'tool_calls' => $step->toolCalls, // Keep raw tool calls here
                    'tool_results' => $step->toolResults, // Keep raw tool results here
                    'additional_content' => $step->additionalContent,
                ];
                $result->addStep($stepDataForAgentResult);

                // Start a span for this step
                $stepSpan = $this->tracer?->startSpan(
                    "step_{$stepIndex}",
                    'llm_step', // Type for a step within an agent execution
                    [
                        'step_index' => $stepIndex,
                        'text_generated' => $step->text,
                        'finish_reason' => $this->enumToString($step->finishReason),
                        'usage' => $this->extractUsageData($step->usage),
                        'meta' => $step->meta ? get_object_vars($step->meta) : null,
                        'raw_tool_calls' => $step->toolCalls, // Store raw calls from Prism
                    ],
                    $parentSpanId // Parent is the main agent execution span
                );

                // Trace tool calls and handoffs within this step
                if (!empty($step->toolResults)) {
                    $toolCallIndex = 0;
                    foreach ($step->toolResults as $toolResult) {
                        $toolName = $toolResult->toolName ?? 'unknown_tool';
                        $isHandoff = in_array($toolName, $agentToolNames);
                        $spanType = $isHandoff ? 'handoff' : 'tool_call';

                        $toolSpan = $this->tracer?->startSpan(
                            "tool_{$toolName}_{$toolCallIndex}", // Name includes index for uniqueness
                            $spanType,
                            [
                                'tool_call_id' => $toolResult->toolCallId, // From Prism response
                                'tool_name' => $toolName,
                                'arguments' => $toolResult->args ?? [],
                                'is_agent_tool' => $isHandoff,
                                // Result will be added when the span ends
                            ],
                            $stepSpan?->id // Parent is the current step span
                        );

                        // End the tool/handoff span immediately (assuming synchronous execution for now)
                        // TODO: Handle async tool calls if needed
                        $this->tracer?->endSpan(
                            $toolSpan?->id,
                            'success', // Assuming success, error handling might need adjustment
                            ['result' => $toolResult->result ?? null]
                        );
                        $toolCallIndex++;
                    }
                }

                // End the step span
                $this->tracer?->endSpan($stepSpan?->id, 'success');
                $stepIndex++;
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