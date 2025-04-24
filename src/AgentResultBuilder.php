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
