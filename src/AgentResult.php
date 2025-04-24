<?php

namespace Grpaiva\PrismAgents;

use Illuminate\Support\Collection;

class AgentResult
{
    /**
     * The agent that produced this result
     * 
     * @var Agent|null
     */
    protected ?Agent $agent = null;

    /**
     * The input that was given to the agent
     * 
     * @var mixed
     */
    protected $input = null;

    /**
     * The output from the agent
     *
     * @var string|null
     */
    protected ?string $output = null;

    /**
     * Tool results collected during execution
     *
     * @var array
     */
    protected array $toolResults = [];

    /**
     * All tool calls made during execution (including IDs)
     *
     * @var array
     */
    protected array $allToolCalls = [];

    /**
     * Steps taken during execution
     *
     * @var array
     */
    protected array $steps = [];

    /**
     * Structured output if any
     *
     * @var mixed
     */
    protected $structuredOutput = null;

    /**
     * Error message if any
     * 
     * @var string|null
     */
    protected ?string $error = null;
    
    /**
     * Response metadata
     * 
     * @var array
     */
    protected array $metadata = [];

    /**
     * The LLM provider used
     * 
     * @var string|null
     */
    protected ?string $provider = null;

    /**
     * The LLM model used
     * 
     * @var string|null
     */
    protected ?string $model = null;

    /**
     * The system message used
     * 
     * @var string|null
     */
    protected ?string $systemMessage = null;

    /**
     * Protected constructor to enforce static factory methods
     * 
     * @param Agent|null $agent
     * @param mixed $input
     */
    protected function __construct(?Agent $agent = null, $input = null)
    {
        $this->agent = $agent;
        $this->input = $input;
    }
    
    /**
     * Create a new result instance
     * 
     * @param Agent|null $agent
     * @param mixed $input
     * @return static
     */
    public static function create(?Agent $agent = null, $input = null): static
    {
        return new static($agent, $input);
    }

    /**
     * Set the output
     *
     * @param string $output
     * @return $this
     */
    public function setOutput(string $output): self
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Get the output
     *
     * @return string|null
     */
    public function getOutput(): ?string
    {
        return $this->output;
    }

    /**
     * Set an error message
     * 
     * @param string $error
     * @return $this
     */
    public function setError(string $error): self
    {
        $this->error = $error;
        return $this;
    }

    /**
     * Get the error message
     * 
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }
    
    /**
     * Set response metadata
     * 
     * @param array $metadata
     * @return $this
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
    
    /**
     * Get response metadata
     * 
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set the provider
     *
     * @param string $provider
     * @return $this
     */
    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Get the provider
     *
     * @return string|null
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Set the model
     *
     * @param string $model
     * @return $this
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Get the model
     *
     * @return string|null
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Set the system message
     *
     * @param string $systemMessage
     * @return $this
     */
    public function setSystemMessage(string $systemMessage): self
    {
        $this->systemMessage = $systemMessage;
        return $this;
    }

    /**
     * Get the system message
     *
     * @return string|null
     */
    public function getSystemMessage(): ?string
    {
        return $this->systemMessage;
    }

    /**
     * Check if the result is successful (no error)
     * 
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->error === null;
    }

    /**
     * Alias for isSuccess() for compatibility
     * 
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->isSuccess();
    }

    /**
     * Add a tool result
     *
     * @param string $toolName
     * @param mixed $result
     * @param string|null $toolCallId
     * @param array $args
     * @return $this
     */
    public function addToolResult(string $toolName, $result, ?string $toolCallId = null, array $args = []): self
    {
        $toolResult = [
            'toolName' => $toolName,
            'result' => $result,
        ];
        
        // Add tool call ID if provided
        if ($toolCallId) {
            $toolResult['toolCallId'] = $toolCallId;
        }
        
        // Add arguments if provided
        if (!empty($args)) {
            $toolResult['args'] = $args;
        }
        
        $this->toolResults[] = $toolResult;
        return $this;
    }

    /**
     * Get all tool results
     *
     * @return array
     */
    public function getToolResults(): array
    {
        return $this->toolResults;
    }

    /**
     * Add a tool call
     *
     * @param string $toolName
     * @param string $toolCallId
     * @param array $args
     * @return $this
     */
    public function addToolCall(string $toolName, string $toolCallId, array $args = []): self
    {
        $this->allToolCalls[] = [
            'name' => $toolName,
            'id' => $toolCallId,
            'args' => $args,
        ];
        return $this;
    }

    /**
     * Get all tool calls
     *
     * @return array
     */
    public function getAllToolCalls(): array
    {
        return $this->allToolCalls;
    }

    /**
     * Add a step
     *
     * @param mixed $step
     * @return $this
     */
    public function addStep($step): self
    {
        $this->steps[] = $step;
        return $this;
    }

    /**
     * Get all steps
     *
     * @return array
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Set structured output
     *
     * @param mixed $output
     * @return $this
     */
    public function setStructuredOutput($output): self
    {
        $this->structuredOutput = $output;
        return $this;
    }

    /**
     * Get structured output
     *
     * @return mixed
     */
    public function getStructuredOutput()
    {
        return $this->structuredOutput;
    }

    /**
     * Get the agent that produced this result
     * 
     * @return Agent|null
     */
    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    /**
     * Get the input that was given to the agent
     * 
     * @return mixed
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Check if the result has output
     *
     * @return bool
     */
    public function hasOutput(): bool
    {
        return !empty($this->output);
    }

    /**
     * Convert the result to an array for input to another agent
     *
     * @return array
     */
    public function toInputArray(): array
    {
        $messages = [];
        
        // Add output if exists
        if ($this->output) {
            $messages[] = [
                'role' => 'assistant',
                'content' => $this->output,
            ];
        }
        
        // Add tool calls and results
        foreach ($this->toolResults as $toolResult) {
            $messages[] = [
                'role' => 'tool',
                'toolName' => $toolResult['toolName'],
                'content' => is_string($toolResult['result']) 
                    ? $toolResult['result'] 
                    : json_encode($toolResult['result']),
            ];
        }
        
        return $messages;
    }

    /**
     * Convert the result to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'agent' => $this->agent ? $this->agent->getName() : null,
            'provider' => $this->provider,
            'model' => $this->model,
            'input' => $this->input,
            'output' => $this->output,
            'toolResults' => $this->toolResults,
            'allToolCalls' => $this->allToolCalls,
            'steps' => $this->steps,
            'structuredOutput' => $this->structuredOutput,
            'systemMessage' => $this->systemMessage,
            'error' => $this->error,
            'success' => $this->isSuccess(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Get the result as string (output)
     * 
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->output;
    }

    /**
     * Load response data from Prism format
     * 
     * @param array $responseData
     * @return $this
     */
    public function loadFromPrismResponse(array $responseData): self
    {
        // If this is wrapped in a response key, unwrap it
        if (isset($responseData['response']) && is_array($responseData['response'])) {
            // Handle case where the response is under a response key
            // This happens with the Prism\Prism\Text\Response class
            if (isset($responseData['response']['Prism\\Prism\\Text\\Response'])) {
                return $this->loadFromPrismResponse($responseData['response']['Prism\\Prism\\Text\\Response']);
            }
            
            $responseData = $responseData['response'];
        }
        
        // Extract main output
        if (isset($responseData['text'])) {
            $this->setOutput($responseData['text']);
        }
        
        // Extract provider/model info from meta
        if (isset($responseData['meta'])) {
            if (isset($responseData['meta']['model'])) {
                $this->setModel($responseData['meta']['model']);
            }
            
            // Provider could be determined from model name or other factors
            if (isset($responseData['meta']['model']) && strpos($responseData['meta']['model'], 'gpt-') === 0) {
                $this->setProvider('openai');
            }
        }
        
        // Extract steps
        if (isset($responseData['steps'])) {
            foreach ($responseData['steps'] as $step) {
                // Add step using our Prism-specific method
                $this->addPrismStep($step);
            }
        }
        
        // Extract usage data into metadata
        if (isset($responseData['usage'])) {
            $metadata = $this->getMetadata();
            $metadata['usage'] = $responseData['usage'];
            $this->setMetadata($metadata);
        }
        
        return $this;
    }

    /**
     * Load response data from OpenAI format (alias for loadFromPrismResponse)
     * 
     * @param array $responseData
     * @return $this
     */
    public function loadFromOpenAIResponse(array $responseData): self
    {
        return $this->loadFromPrismResponse($responseData);
    }

    /**
     * Add a tool call using a Prism ToolCall object
     *
     * @param \Prism\Prism\ValueObjects\ToolCall $toolCall
     * @return $this
     */
    public function addPrismToolCall(\Prism\Prism\ValueObjects\ToolCall $toolCall): self
    {
        $this->allToolCalls[] = [
            'name' => $toolCall->name ?? null,
            'id' => $toolCall->id ?? null,
            'args' => $toolCall->args ?? [],
        ];
        return $this;
    }

    /**
     * Add a tool result from a Prism ToolCall result
     *
     * @param \Prism\Prism\ValueObjects\ToolCall $toolCall
     * @param mixed $result The result from the tool
     * @return $this
     */
    public function addPrismToolResult(\Prism\Prism\ValueObjects\ToolCall $toolCall, $result): self
    {
        $toolResult = [
            'toolName' => $toolCall->name ?? 'unknown',
            'toolCallId' => $toolCall->id ?? null,
            'args' => $toolCall->args ?? [],
            'result' => $result,
        ];
        
        $this->toolResults[] = $toolResult;
        return $this;
    }

    /**
     * Add a step from Prism LLM response
     *
     * @param array $prismStep
     * @return $this
     */
    public function addPrismStep(array $prismStep): self
    {
        // Create a step structure compatible with our format
        $step = [
            'text' => $prismStep['text'] ?? '',
            'finish_reason' => $prismStep['finishReason'] ?? null,
            'tool_calls' => [],
            'tool_results' => [],
            'additional_content' => $prismStep['additionalContent'] ?? [],
        ];
        
        // Add tool calls if present
        if (!empty($prismStep['toolCalls'])) {
            $step['tool_calls'] = $prismStep['toolCalls'];
            
            // Also add to our allToolCalls array
            foreach ($prismStep['toolCalls'] as $toolCall) {
                if (is_object($toolCall) && get_class($toolCall) === 'Prism\Prism\ValueObjects\ToolCall') {
                    $this->addPrismToolCall($toolCall);
                }
            }
        }
        
        // Add tool results if present
        if (!empty($prismStep['toolResults'])) {
            $step['tool_results'] = $prismStep['toolResults'];
        }
        
        // Add usage if present
        if (isset($prismStep['usage'])) {
            $step['usage'] = $prismStep['usage'];
        }
        
        // Add the step
        $this->steps[] = $step;
        
        return $this;
    }
} 