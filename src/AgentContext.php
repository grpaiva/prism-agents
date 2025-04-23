<?php

namespace Grpaiva\PrismAgents;

use Illuminate\Support\Collection;

class AgentContext
{
    /**
     * The context data store
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Parent context if nested
     *
     * @var AgentContext|null
     */
    protected ?AgentContext $parent = null;
    
    /**
     * The name of this context
     * 
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * Create a new AgentContext instance
     *
     * @param array $data Initial context data
     * @param AgentContext|null $parent Parent context if nested
     */
    protected function __construct(array $data = [], ?AgentContext $parent = null)
    {
        $this->data = $data;
        $this->parent = $parent;
    }
    
    /**
     * Static factory method for creating contexts
     * 
     * @param string|null $name Optional context name
     * @return static
     */
    public static function as(?string $name = null): static
    {
        $context = new static();
        if ($name) {
            $context->name = $name;
        }
        return $context;
    }
    
    /**
     * Set the initial data for this context
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
     * Set a parent context
     * 
     * @param AgentContext $parent
     * @return $this
     */
    public function withParent(AgentContext $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Get a value from the context
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        // Check if the key exists in this context
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }
        
        // If not found and we have a parent, check there
        if ($this->parent) {
            return $this->parent->get($key, $default);
        }
        
        // Otherwise return the default
        return $default;
    }

    /**
     * Set a value in the context
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function set(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Check if a key exists in the context
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data) || ($this->parent && $this->parent->has($key));
    }

    /**
     * Remove a key from the context
     *
     * @param string $key
     * @return $this
     */
    public function remove(string $key): self
    {
        unset($this->data[$key]);
        return $this;
    }

    /**
     * Get all data in this context (not including parent)
     *
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Merge all context data including parent contexts
     *
     * @return array
     */
    public function merged(): array
    {
        if (!$this->parent) {
            return $this->data;
        }
        
        // Parent data comes first, then our data overwrites any overlapping keys
        return array_merge($this->parent->merged(), $this->data);
    }

    /**
     * Create a new child context
     *
     * @param array $data
     * @return AgentContext
     */
    public function createChild(array $data = []): AgentContext
    {
        return (new static($data, $this));
    }

    /**
     * Get the parent context
     *
     * @return AgentContext|null
     */
    public function getParent(): ?AgentContext
    {
        return $this->parent;
    }
    
    /**
     * Get the context name
     * 
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }
} 