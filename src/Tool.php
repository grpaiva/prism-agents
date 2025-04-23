<?php

namespace Grpaiva\PrismAgents;

use Closure;
use InvalidArgumentException;
use Prism\Prism\Tool as PrismTool;
use Grpaiva\PrismAgents\Exceptions\ToolExecutionException;

/**
 * Agent Tool class that extends Prism's Tool functionality
 * with agent-specific features
 */
class Tool
{
    /**
     * The wrapped Prism tool
     */
    protected PrismTool $prismTool;
    
    /**
     * Tool handler (callable or Agent)
     */
    protected $handler;
    
    /**
     * Protected constructor to enforce static factory methods
     * 
     * @param string $name Tool name
     */
    protected function __construct(string $name = '')
    {
        $this->prismTool = new PrismTool();
        
        if ($name) {
            $this->prismTool->as($name);
        }
    }
    
    /**
     * Static factory method for tool creation
     * 
     * @param string $name
     * @return static
     */
    public static function as(string $name): static
    {
        return new static($name);
    }

    /**
     * Set the tool description
     * 
     * @param string $description
     * @return $this
     */
    public function for(string $description): self
    {
        $this->prismTool->for($description);
        return $this;
    }

    /**
     * Set the tool handler function or agent
     * 
     * @param callable|Agent $handler
     * @return $this
     */
    public function using(callable|Agent $handler): self
    {
        $this->handler = $handler;
        
        if ($handler instanceof Closure || is_callable($handler)) {
            $this->prismTool->using($handler);
        }
        
        return $this;
    }
    
    /**
     * Add a string parameter
     * 
     * @param string $name
     * @param string $description
     * @param bool $required
     * @return $this
     */
    public function withStringParameter(string $name, string $description, bool $required = true): self
    {
        $this->prismTool->withStringParameter($name, $description, $required);
        return $this;
    }
    
    /**
     * Add a number parameter
     * 
     * @param string $name
     * @param string $description
     * @param bool $required
     * @return $this
     */
    public function withNumberParameter(string $name, string $description, bool $required = true): self
    {
        $this->prismTool->withNumberParameter($name, $description, $required);
        return $this;
    }
    
    /**
     * Add a boolean parameter
     * 
     * @param string $name
     * @param string $description
     * @param bool $required
     * @return $this
     */
    public function withBooleanParameter(string $name, string $description, bool $required = true): self
    {
        $this->prismTool->withBooleanParameter($name, $description, $required);
        return $this;
    }

    /**
     * Execute the tool with given arguments
     *
     * @param array $arguments
     * @param AgentContext|null $context
     * @return string|AgentResult
     * @throws ToolExecutionException
     */
    public function execute(array $arguments, ?AgentContext $context = null): string|AgentResult
    {
        try {
            // Validate required parameters
            foreach ($this->getRequiredParameters() as $required) {
                if (!isset($arguments[$required])) {
                    throw new InvalidArgumentException("Missing required parameter: {$required}");
                }
            }
            
            // If handler is an Agent, this is an agent-as-tool call
            if ($this->handler instanceof Agent) {
                // Execute the agent as a tool via the Runner
                $agent = $this->handler;
                $runner = new Runner();
                return $runner->runAgent($agent, $arguments, $context);
            }
            
            // Otherwise, use Prism's tool handling
            return $this->prismTool->handle($arguments);
        } catch (\Throwable $e) {
            throw new ToolExecutionException("Error executing tool '{$this->getName()}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the tool definition for the LLM
     *
     * @return array
     */
    public function toDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->prismTool->parameters(),
                    'required' => $this->prismTool->requiredParameters(),
                ],
            ]
        ];
    }
    
    /**
     * Get the Prism Tool instance
     * 
     * @return PrismTool
     */
    public function getPrismTool(): PrismTool
    {
        return $this->prismTool;
    }
    
    /**
     * Get the tool name
     * 
     * @return string
     */
    public function getName(): string
    {
        return $this->prismTool->name();
    }
    
    /**
     * Get the tool description
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return $this->prismTool->description();
    }
    
    /**
     * Get the tool parameters
     * 
     * @return array
     */
    public function getParameters(): array
    {
        return $this->prismTool->parameters();
    }
    
    /**
     * Get required parameters
     * 
     * @return array
     */
    public function getRequiredParameters(): array
    {
        return $this->prismTool->requiredParameters();
    }
} 