<?php

namespace Grpaiva\PrismAgents;

class GuardrailResult
{
    /**
     * Whether the guardrail check passed
     */
    protected bool $passes;

    /**
     * Optional error message
     */
    protected ?string $message = null;

    /**
     * Optional error code
     */
    protected ?int $code = null;

    /**
     * Additional data
     */
    protected array $data = [];

    /**
     * Protected constructor to enforce static factory methods
     */
    protected function __construct(bool $passes)
    {
        $this->passes = $passes;
    }

    /**
     * Create a passing result
     *
     * @return static
     */
    public static function pass(): self
    {
        return new static(true);
    }

    /**
     * Create a failing result
     *
     * @return static
     */
    public static function fail(string $message, int $code = 400): self
    {
        $instance = new static(false);
        $instance->message = $message;
        $instance->code = $code;

        return $instance;
    }

    /**
     * Add data to the result
     *
     * @return $this
     */
    public function withData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Set an error message for the result
     *
     * @return $this
     */
    public function withMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Set an error code for the result
     *
     * @return $this
     */
    public function withCode(int $code): self
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Check if the result passes
     */
    public function passes(): bool
    {
        return $this->passes;
    }

    /**
     * Check if the result fails
     */
    public function fails(): bool
    {
        return ! $this->passes;
    }

    /**
     * Get the error message
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Get the error code
     */
    public function getCode(): ?int
    {
        return $this->code;
    }

    /**
     * Get additional data
     */
    public function getData(): array
    {
        return $this->data;
    }
}
