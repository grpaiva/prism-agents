<?php

namespace Grpaiva\PrismAgents;

use Prism\Prism\Concerns\ConfiguresClient;
use Prism\Prism\Concerns\ConfiguresGeneration;
use Prism\Prism\Concerns\ConfiguresModels;
use Prism\Prism\Concerns\ConfiguresProviders;
use Prism\Prism\Concerns\ConfiguresTools;
use Prism\Prism\Concerns\HasTools;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Tool;

class Agent
{
    use ConfiguresClient, ConfiguresGeneration, ConfiguresModels, ConfiguresProviders, ConfiguresTools, HasTools;

    /**
     * The unique name of the agent
     */
    protected string $name;

    /**
     * Instructions for the agent
     */
    protected string $instructions = '';

    /**
     * Description of this agent when used as a handoff target
     */
    protected ?string $handoffDescription = null;

    /**
     * Guardrails for validating input
     */
    protected array $inputGuardrails = [];

    /**
     * Protected constructor to enforce use of static factory methods
     */
    protected function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Create a new agent instance with builder pattern
     */
    public static function as(string $name): static
    {
        return new static($name);
    }

    /**
     * Set the agent's instructions
     *
     * @return $this
     */
    public function withInstructions(string $instructions): self
    {
        $this->instructions = $instructions;

        return $this;
    }

    /**
     * Set the handoff description when used as a tool
     *
     * @return $this
     */
    public function withHandoffDescription(string $description): self
    {
        $this->handoffDescription = $description;

        return $this;
    }

    /**
     * Set input guardrails
     *
     * @return $this
     */
    public function withInputGuardrails(array $guardrails): self
    {
        $this->inputGuardrails = $guardrails;

        return $this;
    }

    /**
     * Create a tool representation of this agent
     */
    public function asTool(): Tool
    {
        $toolName = $this->name;
        $toolDescription = $this->handoffDescription ?? "Agent: {$this->name}";

        return \Prism\Prism\Facades\Tool::as($toolName)
            ->for($toolDescription)
            ->withStringParameter('input', 'Input for the agent', true)
            ->using(function (string $input) {
                // Execute this agent with the given arguments
                $runner = new Runner;

                return $runner->runAgent($this, $input)->getOutput();
            });
    }

    /**
     * Get the agent's name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the agent's instructions
     */
    public function getInstructions(): string
    {
        return $this->instructions;
    }

    /**
     * Get the agent's handoff description
     */
    public function getHandoffDescription(): ?string
    {
        return $this->handoffDescription;
    }

    /**
     * Get the agent's provider
     */
    public function getProvider(): Provider
    {
        return Provider::from($this->providerKey);
    }

    /**
     * Get the agent's tools
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Get input guardrails
     */
    public function getInputGuardrails(): array
    {
        return $this->inputGuardrails;
    }

    /**
     * Get the model
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Get max steps
     */
    public function getMaxSteps(): ?int
    {
        return $this->maxSteps;
    }

    /**
     * Get client options
     */
    public function getClientOptions(): array
    {
        return $this->clientOptions;
    }

    /**
     * Get client retry options
     */
    public function getClientRetry(): array
    {
        return $this->clientRetry;
    }

    /**
     * Get the max tokens
     */
    public function getMaxTokens(): ?int
    {
        return $this->maxTokens;
    }

    /**
     * Get the temperature
     */
    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    /**
     * Get the top P
     */
    public function getTopP(): ?float
    {
        return $this->topP;
    }

    /**
     * Get the tool choice
     */
    public function getToolChoice(): string|ToolChoice|null
    {
        return $this->toolChoice;
    }
}
