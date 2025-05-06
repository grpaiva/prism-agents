<?php

namespace Grpaiva\PrismAgents;

abstract class Guardrail
{
    /**
     * The name of this guardrail
     */
    protected ?string $name = null;

    /**
     * Create a new guardrail instance
     */
    public static function as(?string $name = null): static
    {
        $instance = new static;
        if ($name) {
            $instance->name = $name;
        }

        return $instance;
    }

    /**
     * Get the guardrail name
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the guardrail name
     *
     * @return $this
     */
    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Check the input against this guardrail
     *
     * @param  string|array  $input
     */
    abstract public function check($input, AgentContext $context): GuardrailResult;
}
