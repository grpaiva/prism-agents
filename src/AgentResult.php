<?php

namespace Grpaiva\PrismAgents;

class AgentResult
{
    /**
     * The agent that produced this result
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
     */
    protected ?string $output = null;

    /**
     * Tool results collected during execution
     */
    protected array $toolResults = [];

    /**
     * Steps taken during execution
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
     */
    protected ?string $error = null;

    /**
     * Response metadata
     */
    protected array $metadata = [];

    /**
     * Protected constructor to enforce static factory methods
     *
     * @param  mixed  $input
     */
    protected function __construct(?Agent $agent = null, $input = null)
    {
        $this->agent = $agent;
        $this->input = $input;
    }

    /**
     * Create a new result instance
     *
     * @param  mixed  $input
     */
    public static function create(?Agent $agent = null, $input = null): static
    {
        return new static($agent, $input);
    }

    /**
     * Set the output
     *
     * @return $this
     */
    public function setOutput(string $output): self
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Get the output
     */
    public function getOutput(): ?string
    {
        return $this->output;
    }

    /**
     * Set an error message
     *
     * @return $this
     */
    public function setError(string $error): self
    {
        $this->error = $error;

        return $this;
    }

    /**
     * Get the error message
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Set response metadata
     *
     * @return $this
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Get response metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if the result is successful (no error)
     */
    public function isSuccess(): bool
    {
        return $this->error === null;
    }

    /**
     * Add a tool result
     *
     * @param  mixed  $result
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
     */
    public function getToolResults(): array
    {
        return $this->toolResults;
    }

    /**
     * Add a step
     *
     * @param  mixed  $step
     * @return $this
     */
    public function addStep($step): self
    {
        $this->steps[] = $step;

        return $this;
    }

    /**
     * Get all steps
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Set structured output
     *
     * @param  mixed  $output
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
     */
    public function hasOutput(): bool
    {
        return ! empty($this->output);
    }

    /**
     * Convert the result to an array for input to another agent
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
     */
    public function __toString(): string
    {
        return (string) $this->output;
    }
}
