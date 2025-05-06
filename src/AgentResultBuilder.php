<?php

namespace Grpaiva\PrismAgents;

/**
 * Builder class for agent result to enable method chaining
 */
class AgentResultBuilder
{
    /**
     * The agent result
     */
    protected AgentResult $result;

    /**
     * Create a new builder instance
     */
    public function __construct(AgentResult $result)
    {
        $this->result = $result;
    }

    /**
     * Add trace to the result
     *
     * @param  Trace|string  $trace  Trace object or trace name
     */
    public function withTrace(Trace|string $trace): AgentResult
    {
        // If a string is provided, create a trace with that name
        if (is_string($trace)) {
            $trace = Trace::as($trace);
        }

        $trace->addResult($this->result);

        return $this->result;
    }

    /**
     * Get the underlying agent result
     */
    public function get(): AgentResult
    {
        return $this->result;
    }

    /**
     * Allow direct access to agent result methods
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return $this->result->$name(...$arguments);
    }
}
