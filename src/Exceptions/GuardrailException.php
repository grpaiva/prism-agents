<?php

namespace Grpaiva\PrismAgents\Exceptions;

use Exception;

class GuardrailException extends Exception
{
    /**
     * Additional data
     */
    protected array $data;

    /**
     * Create a new GuardrailException instance
     */
    public function __construct(string $message = '', int $code = 0, array $data = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    /**
     * Get additional data
     */
    public function getData(): array
    {
        return $this->data;
    }
}
