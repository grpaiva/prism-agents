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
     * Check if the result is successful (no error)
     * 
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->error === null;
    }

    /**
     * Add a tool result
     *
     * @param string $toolName
     * @param mixed $result
     * @return $this
     */
    public function addToolResult(string $toolName, $result): self
    {
        $this->toolResults[] = [
            'toolName' => $toolName,
            'result' => $result,
        ];
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
            'input' => $this->input,
            'output' => $this->output,
            'toolResults' => $this->toolResults,
            'steps' => $this->steps,
            'structuredOutput' => $this->structuredOutput,
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
} 