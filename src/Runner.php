<?php

namespace Grpaiva\PrismAgents;

use Grpaiva\PrismAgents\Exceptions\GuardrailException;
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
     * @var Trace|null
     */
    protected ?Trace $trace = null;

    /**
     * @var int|null
     */
    protected ?int $maxSteps = null;

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
        // Create a context if none is provided
        $context = $context ?? AgentContext::as('runner_context');

        // Start tracing the execution
        $executionId = $this->trace->startExecution(
            $agent->getName(),
            [
                'provider' => $agent->getProvider()?->value,
                'model' => $agent->getModel(),
                'user_id' => $context->get('user_id'),
                'parent_id' => $context->get('parent_execution_id'),
            ]
        );
        
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
                        $prismRequest->withMessage($message['role'], $message['content']);
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
            
            // End the execution with success
            $this->trace->endExecution([
                'status' => 'completed',
                'total_tokens' => $metadata['usage']['total_tokens'] ?? null,
                'prompt_tokens' => $metadata['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $metadata['usage']['completion_tokens'] ?? null,
            ]);
            
            return $result;
        } catch (\Exception $e) {
            // Log and trace the error
            Log::error('Error in agent run: ' . $e->getMessage(), [
                'agent' => $agent->getName(),
                'exception' => $e,
            ]);
            
            // End execution with error
            $this->trace->endExecution([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
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
                // Skip if step is not the expected type
                if (!is_object($step)) {
                    \Illuminate\Support\Facades\Log::warning('Unexpected step type in response', ['step' => $step]);
                    continue;
                }
                
                // Start a new step
                $stepId = $this->trace->startStep('step', [
                    'text' => property_exists($step, 'text') ? $step->text : null,
                    'finish_reason' => $this->enumToString($step->finishReason ?? null),
                    'usage' => $this->extractUsageData($step->usage ?? null),
                ]);

                // Process tool calls and results
                if (!empty($step->toolCalls)) {
                    foreach ($step->toolCalls as $toolCall) {
                        $toolCallId = $this->trace->startToolCall($toolCall->name ?? 'unnamed_tool', [
                            'call_id' => $toolCall->id ?? Str::uuid()->toString(),
                            'args' => property_exists($toolCall, 'args') ? $toolCall->args : null,
                        ]);

                        // Find and record corresponding result
                        if (!empty($step->toolResults)) {
                            foreach ($step->toolResults as $toolResult) {
                                if ($toolResult->toolCallId === $toolCall->id) {
                                    $this->trace->recordToolResult($toolCallId, [
                                        'tool_name' => $toolResult->toolName ?? 'unknown',
                                        'args' => property_exists($toolResult, 'args') ? $toolResult->args : null,
                                        'result' => property_exists($toolResult, 'result') ? $toolResult->result : null,
                                    ]);
                                    break;
                                }
                            }
                        }

                        $this->trace->endToolCall($toolCallId);
                    }
                }

                // Record messages
                if (!empty($step->messages)) {
                    foreach ($step->messages as $message) {
                        $this->trace->recordMessage([
                            'content' => property_exists($message, 'content') ? $message->content : null,
                            'tool_calls' => property_exists($message, 'toolCalls') ? $message->toolCalls : null,
                            'additional_content' => property_exists($message, 'additionalContent') ? $message->additionalContent : null,
                        ]);
                    }
                }

                // End the step
                $this->trace->endStep();

                // Add step to result - make sure $result is an AgentResult
                if (method_exists($result, 'addStep')) {
                    $result->addStep([
                        'text' => property_exists($step, 'text') ? $step->text : null,
                        'finish_reason' => $this->enumToString($step->finishReason ?? null),
                        'tool_calls' => property_exists($step, 'toolCalls') ? $step->toolCalls : [],
                        'tool_results' => property_exists($step, 'toolResults') ? $step->toolResults : [],
                        'additional_content' => property_exists($step, 'additionalContent') ? $step->additionalContent : null,
                ]);
                } else {
                    \Illuminate\Support\Facades\Log::warning('Cannot add step: result object does not support addStep method', [
                        'result_type' => get_class($result),
                        'agent' => $agent->getName(),
                    ]);
                }
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
     * Extract provider information from Meta object
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