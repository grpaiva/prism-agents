<?php

namespace Grpaiva\PrismAgents;

use Prism\Prism\Concerns\ConfiguresModels;
use Prism\Prism\Concerns\ConfiguresProviders;
use Prism\Prism\Concerns\HasTools;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Tool;

class Agent
{
    use HasTools, ConfiguresProviders, ConfiguresModels;

    /**
     * The unique name of the agent
     *
     * @var string
     */
    protected string $name;

    /**
     * Instructions for the agent
     *
     * @var string
     */
    protected string $instructions = '';

    /**
     * Description of this agent when used as a handoff target
     * 
     * @var string|null
     */
    protected ?string $handoffDescription = null;

    /**
     * Guardrails for validating input
     * 
     * @var array
     */
    protected array $inputGuardrails = [];

    /**
     * Client options for the agent
     *
     * @var array
     */
    protected array $clientOptions = [];

    /**
     * Maximum number of steps for agent to take
     * 
     * @var int|null
     */
    protected ?int $maxSteps = null;

    /**
     * Protected constructor to enforce use of static factory methods
     */
    protected function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Create a new agent instance with builder pattern
     * 
     * @param string $name
     * @return static
     */
    public static function as(string $name): static
    {
        return new static($name);
    }

    /**
     * Set the agent's instructions
     * 
     * @param string $instructions
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
     * @param string $description
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
     * @param array $guardrails
     * @return $this
     */
    public function withInputGuardrails(array $guardrails): self
    {
        $this->inputGuardrails = $guardrails;
        return $this;
    }

    /**
     * Set maximum number of steps
     * 
     * @param int $steps
     * @return $this
     */
    public function withMaxSteps(int $steps): self
    {
        $this->maxSteps = $steps;
        return $this;
    }

    /**
     * Set client options
     */
    public function withClientOptions(array $options): self
    {
        $this->clientOptions = $options;
        return $this;
    }

    /**
     * Create a tool representation of this agent
     *
     * @return Tool
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
                $runner = new Runner();
                return ($runner->runAgent($this, $input))->getOutput();
            });
    }

    /**
     * Get the agent's name
     * 
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the agent's instructions
     * 
     * @return string
     */
    public function getInstructions(): string
    {
        return $this->instructions;
    }

    /**
     * Get the agent's handoff description
     * 
     * @return string|null
     */
    public function getHandoffDescription(): ?string
    {
        return $this->handoffDescription;
    }

    /**
     * Get the agent's provider
     *
     * @return Provider
     */
    public function getProvider(): Provider
    {
        return $this->provider;
    }

    /**
     * Get the agent's tools
     * 
     * @return array
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Get input guardrails
     * 
     * @return array
     */
    public function getInputGuardrails(): array
    {
        return $this->inputGuardrails;
    }


    /**
     * Get the model
     * 
     * @return string|null
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Get max steps
     * 
     * @return int|null
     */
    public function getMaxSteps(): ?int
    {
        return $this->maxSteps;
    }

    /**
     * Get client options
     *
     * @return array
     */
    public function getClientOptions(): array
    {
        return $this->clientOptions;
    }
} 