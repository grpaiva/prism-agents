<?php

namespace Grpaiva\PrismAgents;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Grpaiva\PrismAgents\Models\AgentTrace;
use Grpaiva\PrismAgents\Models\AgentSpan;

class Trace
{
    /**
     * The trace ID
     *
     * @var string
     */
    protected string $traceId;

    /**
     * The spans in this trace
     *
     * @var array
     */
    protected array $spans = [];

    /**
     * Stack of active span IDs
     *
     * @var array
     */
    protected array $activeSpans = [];

    /**
     * Whether tracing is enabled
     *
     * @var bool
     */
    protected bool $enabled;

    /**
     * The database connection to use
     *
     * @var string|null
     */
    protected ?string $connection;

    /**
     * Optional trace name
     * 
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * The AgentTrace model instance
     *
     * @var AgentTrace|null
     */
    protected ?AgentTrace $traceModel = null;

    /**
     * Protected constructor to enforce use of static factory methods
     *
     * @param string|null $traceId
     */
    protected function __construct(?string $traceId = null)
    {
        // If a trace ID is provided without the trace_ prefix, add it
        if ($traceId && strpos($traceId, 'trace_') !== 0) {
            $traceId = 'trace_' . $traceId;
        } else if (!$traceId) {
            // Generate a new trace ID with the correct format
            $traceId = 'trace_' . Str::uuid()->toString();
        }
        
        $this->traceId = $traceId;
        
        // Load configuration
        $this->enabled = Config::get('prism-agents.tracing.enabled', true);
        
        // If no specific connection is provided, use the default database connection
        $this->connection = Config::get('prism-agents.tracing.connection') ?: config('database.default');
        
        // Verify table existence
        $this->verifyTraceTable();
        
        // Try to load or create the trace model
        $this->initTraceModel();
    }

    /**
     * Verify if the trace tables exist in the database
     * 
     * @return bool
     */
    protected function verifyTraceTable(): bool
    {
        try {
            $tracesExist = DB::connection($this->connection)
                ->getSchemaBuilder()
                ->hasTable('prism_agent_traces');
                
            $spansExist = DB::connection($this->connection)
                ->getSchemaBuilder()
                ->hasTable('prism_agent_spans');
                
            if (!$tracesExist || !$spansExist) {
                \Illuminate\Support\Facades\Log::warning('Trace tables do not exist in the database. Tracing will be disabled.', [
                    'traces_exist' => $tracesExist,
                    'spans_exist' => $spansExist,
                    'connection' => $this->connection
                ]);
                $this->enabled = false;
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error verifying trace tables: " . $e->getMessage(), [
                'connection' => $this->connection,
                'exception' => $e
            ]);
            
            // Disable tracing on error
            $this->enabled = false;
            return false;
        }
    }

    /**
     * Initialize the trace model
     * 
     * @return void
     */
    protected function initTraceModel(): void
    {
        if (!$this->enabled) {
            return;
        }
        
        try {
            // Try to find existing trace
            $this->traceModel = AgentTrace::findTrace($this->traceId);
            
            // If not found, create a new one
            if (!$this->traceModel) {
                $this->traceModel = new AgentTrace([
                    'id' => $this->traceId,
                    'created_at' => now(),
                    'workflow_name' => $this->name,
                ]);
                
                if ($this->connection) {
                    $this->traceModel->setConnection($this->connection);
                }
                
                $this->traceModel->save();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error initializing trace model: " . $e->getMessage(), [
                'trace_id' => $this->traceId,
                'exception' => $e
            ]);
            $this->enabled = false;
        }
    }

    /**
     * Create a new trace instance
     * 
     * @param string|null $name Optional trace name
     * @return static
     */
    public static function as(?string $name = null): static
    {
        $instance = new static();
        if ($name) {
            $instance->name = $name;
            
            // Update the trace model with the name
            if ($instance->traceModel) {
                $instance->traceModel->workflow_name = $name;
                $instance->traceModel->save();
            }
        }
        return $instance;
    }

    /**
     * Retrieve a trace by its name or ID
     * 
     * @param string $nameOrId The trace name or ID to retrieve
     * @return static|null The trace instance if found, null otherwise
     */
    public static function retrieve(string $nameOrId): ?static
    {
        $trace = new static($nameOrId);
        
        // If we couldn't initialize the trace model, this trace doesn't exist
        if (!$trace->traceModel) {
            // Try to find by workflow_name
            $traceModel = AgentTrace::where('workflow_name', $nameOrId)->first();
            if (!$traceModel) {
                return null;
            }
            
            // Create a new instance with the found trace ID
            $trace = new static($traceModel->id);
        }
        
        // Load the spans from the database
        try {
            $spans = AgentSpan::where('trace_id', $trace->traceId)
                ->orderBy('started_at')
                ->get();
                
            foreach ($spans as $span) {
                $trace->spans[$span->id] = [
                    'id' => $span->id,
                    'trace_id' => $span->trace_id,
                    'parent_id' => $span->parent_id,
                    'name' => $span->agent_name,
                    'type' => $span->span_type,
                    'started_at' => $span->started_at,
                    'ended_at' => $span->ended_at,
                    'duration' => $span->duration_ms,
                    'metadata' => $span->span_data ?? [],
                ];
            }
            
            return $trace;
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    /**
     * Add a result to the trace
     *
     * @param AgentResult $result
     * @return $this
     */
    public function addResult(AgentResult $result): self
    {
        if (!$this->enabled) {
            return $this;
        }

        // Create a root span for the agent execution
        $spanId = $this->startSpan($result->getAgent()->getName(), 'agent_execution');
        
        // Add metadata about the agent
        $this->updateSpan($spanId, [
            'agent' => $result->getAgent()->getName(),
            'provider' => $result->getProvider(),
            'model' => $result->getModel(),
            'input' => $result->getInput(),
            'metadata' => $result->getMetadata(),
            'steps' => $result->getSteps(),
            'tool_calls' => $result->getAllToolCalls(),
            'system_message' => $result->getSystemMessage(),
            'output' => $result->getOutput(),
            'status' => $result->isSuccessful() ? 'success' : 'error',
            'error' => $result->getError(),
        ]);
        
        // Add step spans
        foreach ($result->getSteps() as $index => $step) {
            $stepSpanId = $this->startSpan("step_{$index}", 'llm_step', $spanId);
            $this->updateSpan($stepSpanId, [
                'step_index' => $index,
                'agent' => $result->getAgent()->getName(),
                'text' => $step['text'],
                'finish_reason' => $step['finish_reason'],
                'tools' => collect($step['tool_calls'])->pluck('name')->unique()->values()->toArray(),
                'additional_content' => $step['additional_content'],
            ]);
            
            // Add tool call spans
            foreach ($step['tool_results'] as $toolIdx => $toolResult) {
                $toolSpanId = $this->startSpan("tool_{$toolResult['toolName']}_{$toolIdx}", 'handoff', $stepSpanId);
                $this->updateSpan($toolSpanId, [
                    'tool_name' => $toolResult['toolName'],
                    'args' => $toolResult['args'],
                    'result' => $toolResult['result'],
                ]);
                
                $this->endSpan($toolSpanId);
            }
            
            $this->endSpan($stepSpanId);
        }
        
        // End the root span
        $this->endSpan($spanId);
        
        // Update the trace model with statistics
        if ($this->traceModel) {
            $this->traceModel->calculateCounts();
            $this->traceModel->calculateDuration();
            $this->traceModel->save();
        }
        
        return $this;
    }

    /**
     * Start a new span in the trace
     *
     * @param string $name The name of the span
     * @param string $type The type of span
     * @param string|null $parentId Optional parent span ID
     * @param array $metadata Optional metadata to include
     * @return string The ID of the new span
     */
    public function startSpan(string $name, string $type, ?string $parentId = null, array $metadata = []): string
    {
        if (!$this->enabled) {
            return 'disabled';
        }

        // First, create a model
        $now = Carbon::now();
        $spanId = 'span_' . substr(Str::uuid()->toString(), 0, 24);
        
        // Determine span type and data structure
        $spanData = ['type' => 'function']; // Default to function type
        
        switch ($type) {
            case 'agent_execution':
            case 'agent_run':
                $spanData = [
                    'type' => 'agent',
                    'name' => $name,
                    'output_type' => 'str',
                    'tools' => [],
                    'handoffs' => []
                ];
                break;
                
            case 'handoff':
                $spanData = [
                    'type' => 'handoff',
                    'from_agent' => $metadata['from_agent'] ?? null,
                    'to_agent' => $metadata['to_agent'] ?? $name
                ];
                break;
                
            case 'tool_call':
                $spanData = [
                    'type' => 'function',
                    'name' => $name,
                    'input' => $metadata['args'] ?? null,
                    'output' => $metadata['result'] ?? null,
                ];
                break;
                
            case 'llm_step':
                $spanData = [
                    'type' => 'response',
                    'response_id' => 'resp_' . substr(md5($spanId), 0, 40),
                ];
                break;
                
            default:
                // Handle other types
                $spanData['name'] = $name;
        }
        
        // Save to database
        try {
            $span = new AgentSpan([
                'id' => $spanId,
                'trace_id' => $this->traceId,
                'parent_id' => $parentId,
                'span_data' => $spanData,
                'started_at' => $now,
                'created_at' => $now,
            ]);
            
            if ($this->connection) {
                $span->setConnection($this->connection);
            }
            
            $span->save();
            
            // Keep track of span in memory
            $this->spans[$spanId] = [
                'id' => $spanId,
                'trace_id' => $this->traceId,
                'parent_id' => $parentId,
                'name' => $name,
                'type' => $type,
                'started_at' => $now,
                'ended_at' => null,
                'duration' => null,
                'metadata' => $metadata,
            ];
            
            // Add to active spans
            $this->activeSpans[] = $spanId;
            
            return $spanId;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error creating span: ' . $e->getMessage(), [
                'name' => $name,
                'type' => $type,
                'parent_id' => $parentId,
                'exception' => $e
            ]);
            
            return 'error_' . Str::uuid()->toString();
        }
    }

    /**
     * Update an existing span with additional metadata
     *
     * @param string $spanId The ID of the span to update
     * @param array $metadata The metadata to add to the span
     * @return $this
     */
    public function updateSpan(string $spanId, array $metadata = []): self
    {
        if (!$this->enabled || !isset($this->spans[$spanId])) {
            return $this;
        }
        
        // Update in-memory span data
        $this->spans[$spanId]['metadata'] = array_merge(
            $this->spans[$spanId]['metadata'] ?? [], 
            $metadata
        );
        
        // Update span in database
        try {
            $span = AgentSpan::find($spanId);
            if ($span) {
                // Update span data based on span type
                if ($span->isAgentSpan()) {
                    // For agent spans, update tools and handoffs if present
                    $spanData = $span->span_data;
                    
                    // Extract tools from metadata
                    if (!empty($metadata['tool_calls'])) {
                        $spanData['tools'] = collect($metadata['tool_calls'])
                            ->pluck('toolName')
                            ->unique()
                            ->values()
                            ->toArray();
                    }
                    
                    // Update span data
                    $span->span_data = $spanData;
                } 
                else if ($span->isFunctionSpan()) {
                    // Update function input/output if applicable
                    $spanData = $span->span_data;
                    if (isset($metadata['args'])) {
                        $spanData['input'] = json_encode($metadata['args']);
                    }
                    if (isset($metadata['result'])) {
                        $spanData['output'] = $this->truncateText($metadata['result']);
                    }
                    $span->span_data = $spanData;
                }
                
                $span->save();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating span: ' . $e->getMessage(), [
                'trace_id' => $this->traceId,
                'span_id' => $spanId,
                'exception' => $e
            ]);
        }
        
        return $this;
    }

    /**
     * End a span
     *
     * @param string $spanId The ID of the span to end
     * @param array $metadata Additional metadata to include
     * @return $this
     */
    public function endSpan(string $spanId, array $metadata = []): self
    {
        if (!$this->enabled || !isset($this->spans[$spanId])) {
            return $this;
        }
        
        $now = Carbon::now();
        $span = &$this->spans[$spanId];
        $span['ended_at'] = $now;
        $span['duration'] = $now->diffInMilliseconds($span['started_at']);
        $span['metadata'] = array_merge($span['metadata'] ?? [], $metadata);
        
        // Remove from active spans
        $index = array_search($spanId, $this->activeSpans);
        if ($index !== false) {
            array_splice($this->activeSpans, $index, 1);
        }
        
        // Update span in database
        try {
            $spanModel = AgentSpan::find($spanId);
            if ($spanModel) {
                $spanModel->ended_at = $now;
                $spanModel->duration_ms = $span['duration'];
                
                // Handle error information if present
                if (isset($metadata['error'])) {
                    $spanModel->error = [
                        'message' => $metadata['error'],
                        'data' => [
                            'error' => $metadata['error_message'] ?? $metadata['error']
                        ]
                    ];
                }
                
                $spanModel->save();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error ending span: ' . $e->getMessage(), [
                'trace_id' => $this->traceId,
                'span_id' => $spanId,
                'exception' => $e
            ]);
        }
        
        return $this;
    }

    /**
     * Configure whether tracing is enabled
     * 
     * @param bool $enabled
     * @return $this
     */
    public function withTracingEnabled(bool $enabled = true): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Configure database connection
     * 
     * @param string $connection
     * @return $this
     */
    public function withConnection(string $connection): self
    {
        $this->connection = $connection;
        
        // Update trace model connection if it exists
        if ($this->traceModel) {
            $this->traceModel->setConnection($connection);
        }
        
        return $this;
    }

    /**
     * Truncate text to a maximum length for database storage
     * 
     * @param mixed $text
     * @param int $maxLength
     * @return string|null
     */
    protected function truncateText($text, int $maxLength = 10000): ?string
    {
        if ($text === null) {
            return null;
        }
        
        // Convert non-string values to string
        if (!is_string($text)) {
            $text = is_array($text) || is_object($text) 
                ? json_encode($text) 
                : (string) $text;
        }
        
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        
        return substr($text, 0, $maxLength - 3) . '...';
    }

    /**
     * Get the trace ID
     *
     * @return string
     */
    public function getTraceId(): string
    {
        return $this->traceId;
    }

    /**
     * Get the trace name
     * 
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name ?? $this->traceModel->workflow_name ?? null;
    }

    /**
     * Get all spans
     *
     * @return array
     */
    public function getSpans(): array
    {
        return $this->spans;
    }

    /**
     * Check if a span is active
     *
     * @param string $spanId
     * @return bool
     */
    public function isSpanActive(string $spanId): bool
    {
        return in_array($spanId, $this->activeSpans);
    }
} 