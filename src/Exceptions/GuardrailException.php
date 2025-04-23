<?php

namespace Grpaiva\PrismAgents\Exceptions;

use Exception;

class GuardrailException extends Exception
{
    /**
     * Additional data
     *
     * @var array
     */
    protected array $data;

    /**
     * Create a new GuardrailException instance
     *
     * @param string $message
     * @param int $code
     * @param array $data
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = "", int $code = 0, array $data = [], \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
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
