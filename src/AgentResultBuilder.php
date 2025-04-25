<?php

namespace Grpaiva\PrismAgents;

/**
 * Builder class for agent result to enable method chaining
 */
class AgentResultBuilder
{
    /**
     * The agent result
     *
     * @var AgentResult
     */
    protected AgentResult $result;

    /**
     * Create a new builder instance
     *
     * @param AgentResult $result
     */
    public function __construct(AgentResult $result)
    {
        $this->result = $result;
    }

    /**
     * Add trace to the result
     *
     * @param Trace|string $trace Trace object or trace name
     * @return $this
     */
    public function withTrace(Trace|string $trace): self
    {
        if (is_string($trace)) {
            $trace = Trace::as($trace);
        }
        
        try {
            // Debug log the structure of the result
            \Illuminate\Support\Facades\Log::debug('AgentResult structure before tracing', [
                'result_type' => get_class($this->result),
                'has_agent' => $this->result->getAgent() !== null,
                'agent_name' => $this->result->getAgent() ? $this->result->getAgent()->getName() : 'none',
                'has_output' => $this->result->getOutput() !== null,
                'steps_count' => count($this->result->getSteps()),
                'has_metadata' => $this->result->getMetadata() !== null,
                'has_error' => $this->result->getError() !== null,
            ]);

        $trace->addResult($this->result);
        } catch (\Exception $e) {
            // Log error but don't break execution
            \Illuminate\Support\Facades\Log::error('Error adding result to trace: ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'trace_id' => $trace->getTraceId(),
            ]);
        }
        
        return $this;
    }

    /**
     * Get the underlying agent result
     *
     * @return AgentResult
     */
    public function get(): AgentResult
    {
        return $this->result;
    }

    /**
     * Allow direct access to agent result methods
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return $this->result->$name(...$arguments);
    }
}
