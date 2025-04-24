<?php

namespace Grpaiva\PrismAgents;

use Prism\Prism\Enums\Provider;

class Agent
{
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
     * The tools available to this agent
     * 
     * @var array
     */
    protected array $tools = [];

    /**
     * The model provider to use (OpenAI, Anthropic, etc)
     * 
     * @var Provider|null
     */
    protected ?Provider $provider = null;

    /**
     * The model to use for this agent
     * 
     * @var string|null
     */
    protected ?string $model = null;

    /**
     * Guardrails for validating input
     * 
     * @var array
     */
    protected array $inputGuardrails = [];

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
     * Set the tools for this agent
     * 
     * @param array $tools
     * @return $this
     */
    public function withTools(array $tools): self
    {
        $this->tools = $tools;
        return $this;
    }

    /**
     * Set the provider and model
     * 
     * @param Provider $provider
     * @param string $model
     * @return $this
     */
    public function using(Provider $provider, string $model): self
    {
        $this->provider = $provider;
        $this->model = $model;
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
    public function steps(int $steps): self
    {
        $this->maxSteps = $steps;
        return $this;
    }

    /**
     * Create a tool representation of this agent
     *
     * @return \Prism\Prism\Tool
     */
    public function asTool(): \Prism\Prism\Tool
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
     * Get the provider
     * 
     * @return Provider|null
     */
    public function getProvider(): ?Provider
    {
        return $this->provider;
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
} 