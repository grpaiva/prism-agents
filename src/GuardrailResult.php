<?php

namespace Grpaiva\PrismAgents;

class GuardrailResult
{
    /**
     * Whether the guardrail check passed
     *
     * @var bool
     */
    protected bool $passes;

    /**
     * Optional error message
     *
     * @var string|null
     */
    protected ?string $message = null;

    /**
     * Optional error code
     *
     * @var int|null
     */
    protected ?int $code = null;

    /**
     * Additional data
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Protected constructor to enforce static factory methods
     *
     * @param bool $passes
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
     * @param string $message
     * @param int $code
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
     * @param array $data
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
     * @param string $message
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
     * @param int $code
     * @return $this
     */
    public function withCode(int $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Check if the result passes
     *
     * @return bool
     */
    public function passes(): bool
    {
        return $this->passes;
    }

    /**
     * Check if the result fails
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !$this->passes;
    }

    /**
     * Get the error message
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Get the error code
     *
     * @return int|null
     */
    public function getCode(): ?int
    {
        return $this->code;
    }

    /**
     * Get additional data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}