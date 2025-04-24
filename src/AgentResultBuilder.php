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
     * @return AgentResult
     */
    public function withTrace(Trace|string $trace): AgentResult
    {
        try {
            // If a string is provided, create a trace with that name
            if (is_string($trace)) {
                $trace = Trace::as($trace);
            }

            $trace->addResult($this->result);
        } catch (\Exception $e) {
            // Log the error but don't crash
            \Illuminate\Support\Facades\Log::error("Error adding result to trace", [
                'message' => $e->getMessage(),
                'exception' => $e,
                'trace' => is_object($trace) ? get_class($trace) : (is_string($trace) ? $trace : gettype($trace)),
                'result' => is_object($this->result) ? get_class($this->result) : gettype($this->result),
            ]);
        }
        
        return $this->result;
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
