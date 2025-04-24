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
        
        // Load the spans from the database using the relationship
        try {
            if ($trace->traceModel) {
                // Use eager loading to get all spans with a single query
                $trace->traceModel->load('spans');
                
                // Build the spans array from the loaded spans
                foreach ($trace->traceModel->spans as $span) {
                    $trace->spans[$span->id] = [
                        'id' => $span->id,
                        'trace_id' => $span->trace_id,
                        'parent_id' => $span->parent_id,
                        'name' => $span->agent_name ?? $span->tool_name ?? null,
                        'type' => $span->span_type,
                        'started_at' => $span->started_at,
                        'ended_at' => $span->ended_at,
                        'duration' => $span->duration_ms,
                        'span_data' => $span->span_data,
                        'error' => $span->error,
                    ];
                }
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
     * @param mixed $result AgentResult or string
     * @return $this
     */
    public function addResult($result): self
    {
        if (!$this->enabled) {
            return $this;
        }

        // Make sure we have an AgentResult object
        if (!$result instanceof AgentResult) {
            \Illuminate\Support\Facades\Log::error("Invalid result type passed to Trace::addResult", [
                'expected' => AgentResult::class,
                'got' => is_object($result) ? get_class($result) : gettype($result),
            ]);
            
            // Try to create a fallback AgentResult
            if (is_string($result)) {
                $fallbackResult = AgentResult::create();
                $fallbackResult->setOutput($result);
                $result = $fallbackResult;
            } elseif (is_array($result) && isset($result['text'])) {
                $fallbackResult = AgentResult::create();
                $fallbackResult->setOutput($result['text']);
                $fallbackResult->setMetadata($result);
                $result = $fallbackResult;
            } else {
                // If we can't make sense of the result, log and return
                \Illuminate\Support\Facades\Log::error("Could not create fallback AgentResult", [
                    'result' => $result,
                ]);
                return $this;
            }
        }

        // Get the agent name (with fallback for missing agent)
        $agentName = $result->getAgent() ? $result->getAgent()->getName() : 'unknown_agent';
        
        // Create a root span for the agent execution
        $spanId = $this->startSpan($agentName, 'agent_execution');
        
        // Add metadata about the agent
        $this->updateSpan($spanId, [
            'agent' => $agentName,
            'provider' => $result->getProvider(),
            'model' => $result->getModel(),
                'input' => $result->getInput(),
            'metadata' => $result->getMetadata(),
                'steps' => $result->getSteps(),
            'tool_calls' => $this->convertToolCalls($result->getAllToolCalls()),
            'system_message' => $result->getSystemMessage(),
            'output' => $result->getOutput(),
            'status' => $result->isSuccessful() ? 'success' : 'error',
            'error' => $result->getError(),
        ]);
        
        // Add step spans
        foreach ($result->getSteps() as $index => $step) {
            $stepSpanId = $this->startSpan("step_{$index}", 'llm_step', $spanId);
            
            // Store the raw tool calls for reference - convert objects to arrays if needed
            $toolCallsData = !empty($step['tool_calls']) ? $this->convertToolCalls($step['tool_calls']) : [];
            
            $this->updateSpan($stepSpanId, [
                'step_index' => $index,
                'agent' => $agentName,
                        'text' => $step['text'] ?? '',
                'finish_reason' => $step['finish_reason'] ?? $step['finishReason'] ?? null,
                'tools' => $this->extractToolNames($step['tool_calls'] ?? []),
                'tool_calls' => $toolCallsData, // Store the complete tool call data
                        'additional_content' => $step['additional_content'] ?? [],
            ]);
            
            // Track tool calls by ID for easier reference
            $toolCallsById = [];
            if (!empty($step['tool_calls'])) {
                foreach ($step['tool_calls'] as $toolCall) {
                    $toolCallId = $this->getToolCallId($toolCall);
                    if ($toolCallId) {
                        $toolCallsById[$toolCallId] = $toolCall;
                    }
                }
            }
            
            // Add tool call spans
                if (!empty($step['tool_results'])) {
                    foreach ($step['tool_results'] as $toolIdx => $toolResult) {
                    // Extract data from the tool result which might be an object or array
                    $toolCallId = $this->getToolResultId($toolResult);
                    $toolName = $this->getToolResultName($toolResult);
                    $args = $this->getToolResultArgs($toolResult);
                    $result = $this->getToolResultOutput($toolResult);
                    
                    // Create a more specific name if we have the tool call ID
                    $spanName = $toolCallId ? "tool_{$toolName}_{$toolCallId}" : "tool_{$toolName}_{$toolIdx}";
                    
                    // Create span with tool_call type (representing a model->tool handoff)
                    $toolSpanId = $this->startSpan($spanName, 'tool_call', $stepSpanId);
                    
                    // Enhanced metadata for the tool call
                    $toolMetadata = [
                        'tool_name' => $toolName,
                        'tool_call_id' => $toolCallId,
                        'args' => $args,
                        'result' => $result,
                    ];
                    
                    // If we found a matching tool call with more details, include that
                    if ($toolCallId && isset($toolCallsById[$toolCallId])) {
                        $toolMetadata['original_tool_call'] = $this->convertToolCallToArray($toolCallsById[$toolCallId]);
                    }
                    
                    $this->updateSpan($toolSpanId, $toolMetadata);
                    
                        $this->endSpan($toolSpanId);
                    }
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
     * Convert tool calls to arrays for storage
     * 
     * @param array $toolCalls Array of tool calls which might be objects or arrays
     * @return array Normalized array of tool calls
     */
    protected function convertToolCalls(array $toolCalls): array
    {
        $result = [];
        
        foreach ($toolCalls as $toolCall) {
            $result[] = $this->convertToolCallToArray($toolCall);
        }
        
        return $result;
    }

    /**
     * Convert a single tool call to an array
     * 
     * @param mixed $toolCall Tool call object or array
     * @return array Normalized tool call array
     */
    protected function convertToolCallToArray($toolCall): array
    {
        // If it's already an array, return it
        if (is_array($toolCall)) {
            return $toolCall;
        }
        
        // If it's an object with a toArray method, use that
        if (is_object($toolCall) && method_exists($toolCall, 'toArray')) {
            return $toolCall->toArray();
        }
        
        // If it's a Prism\Prism\ValueObjects\ToolCall object
        if (is_object($toolCall) && get_class($toolCall) === 'Prism\Prism\ValueObjects\ToolCall') {
            return [
                'id' => $toolCall->id ?? null,
                'name' => $toolCall->name ?? null,
                'args' => $toolCall->args ?? [],
            ];
        }
        
        // For any other object, convert public properties to an array
        if (is_object($toolCall)) {
            return get_object_vars($toolCall);
        }
        
        // Fallback to empty array
        return [];
    }

    /**
     * Extract tool names from tool calls
     * 
     * @param array $toolCalls Array of tool calls
     * @return array Array of unique tool names
     */
    protected function extractToolNames(array $toolCalls): array
    {
        $names = [];
        
        foreach ($toolCalls as $toolCall) {
            $name = $this->getToolCallName($toolCall);
            if ($name) {
                $names[] = $name;
            }
        }
        
        return array_values(array_unique($names));
    }

    /**
     * Get tool call ID from a tool call object or array
     * 
     * @param mixed $toolCall
     * @return string|null
     */
    protected function getToolCallId($toolCall): ?string
    {
        if (is_array($toolCall)) {
            return $toolCall['id'] ?? null;
        }
        
        if (is_object($toolCall)) {
            if (isset($toolCall->id)) {
                return $toolCall->id;
            }
        }
        
        return null;
    }

    /**
     * Get tool call name from a tool call object or array
     * 
     * @param mixed $toolCall
     * @return string|null
     */
    protected function getToolCallName($toolCall): ?string
    {
        if (is_array($toolCall)) {
            return $toolCall['name'] ?? null;
        }
        
        if (is_object($toolCall)) {
            if (isset($toolCall->name)) {
                return $toolCall->name;
            }
        }
        
        return null;
    }

    /**
     * Get tool result ID from a tool result object or array
     * 
     * @param mixed $toolResult
     * @return string|null
     */
    protected function getToolResultId($toolResult): ?string
    {
        if (is_array($toolResult)) {
            return $toolResult['toolCallId'] ?? null;
        }
        
        if (is_object($toolResult)) {
            if (isset($toolResult->toolCallId)) {
                return $toolResult->toolCallId;
            }
        }
        
        return null;
    }

    /**
     * Get tool result name from a tool result object or array
     * 
     * @param mixed $toolResult
     * @return string|null
     */
    protected function getToolResultName($toolResult): ?string
    {
        if (is_array($toolResult)) {
            return $toolResult['toolName'] ?? null;
        }
        
        if (is_object($toolResult)) {
            if (isset($toolResult->toolName)) {
                return $toolResult->toolName;
            }
        }
        
        return null;
    }

    /**
     * Get tool result arguments from a tool result object or array
     * 
     * @param mixed $toolResult
     * @return array
     */
    protected function getToolResultArgs($toolResult): array
    {
        if (is_array($toolResult)) {
            return $toolResult['args'] ?? [];
        }
        
        if (is_object($toolResult)) {
            if (isset($toolResult->args)) {
                return is_array($toolResult->args) ? $toolResult->args : [];
            }
        }
        
        return [];
    }

    /**
     * Get tool result output from a tool result object or array
     * 
     * @param mixed $toolResult
     * @return mixed
     */
    protected function getToolResultOutput($toolResult)
    {
        if (is_array($toolResult)) {
            return $toolResult['result'] ?? null;
        }
        
        if (is_object($toolResult)) {
            if (isset($toolResult->result)) {
                return $toolResult->result;
            }
        }
        
        return null;
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
                    'tool_call_id' => $metadata['tool_call_id'] ?? null,
                    'input' => $metadata['args'] ?? null,
                    'output' => $metadata['result'] ?? null,
                ];
                break;
                
            case 'llm_step':
                $spanData = [
                    'type' => 'response',
                    'response_id' => 'resp_' . substr(md5($spanId), 0, 40),
                    'tool_calls' => $metadata['tool_calls'] ?? [],
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
                            ->pluck('name')
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
                    
                    // Store tool call ID if provided
                    if (isset($metadata['tool_call_id'])) {
                        $spanData['tool_call_id'] = $metadata['tool_call_id'];
                    }
                    
                    // Store original tool call data if provided
                    if (isset($metadata['original_tool_call'])) {
                        $spanData['original_tool_call'] = $metadata['original_tool_call'];
                    }
                    
                    $span->span_data = $spanData;
                }
                else if ($span->isResponseSpan()) {
                    // Update response span with tool calls if provided
                    $spanData = $span->span_data;
                    
                    if (isset($metadata['tool_calls'])) {
                        $spanData['tool_calls'] = $metadata['tool_calls'];
                    }
                    
                    if (isset($metadata['text'])) {
                        $spanData['text'] = $metadata['text'];
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

    /**
     * Create a new trace with a specified group ID
     * 
     * @param string $name Trace name
     * @param string|null $groupId Optional group ID to associate with this trace
     * @return static
     */
    public static function withGroup(string $name, ?string $groupId = null): static
    {
        $instance = self::as($name);
        
        if ($groupId && $instance->traceModel) {
            $instance->traceModel->group_id = $groupId;
            $instance->traceModel->save();
        }
        
        return $instance;
    }

    /**
     * Set metadata for this trace
     * 
     * @param array $metadata Metadata to store with the trace
     * @return $this
     */
    public function withMetadata(array $metadata): self
    {
        if (!$this->enabled || !$this->traceModel) {
            return $this;
        }
        
        try {
            $this->traceModel->metadata = $metadata;
            $this->traceModel->save();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error setting trace metadata: " . $e->getMessage(), [
                'trace_id' => $this->traceId,
                'exception' => $e
            ]);
        }
        
        return $this;
    }

    /**
     * Get traces associated with a specific group ID
     * 
     * @param string $groupId The group ID to search for
     * @return \Illuminate\Support\Collection
     */
    public static function getTracesByGroup(string $groupId): \Illuminate\Support\Collection
    {
        return AgentTrace::where('group_id', $groupId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get the associated AgentTrace model
     * 
     * @return AgentTrace|null
     */
    public function getTraceModel(): ?AgentTrace
    {
        return $this->traceModel;
    }

    /**
     * Get hierarchical structure of spans
     * 
     * @return array
     */
    public function getHierarchicalSpans(): array
    {
        if (!$this->traceModel) {
            return [];
        }
        
        return $this->traceModel->getSpanHierarchy();
    }

    /**
     * Get flattened hierarchical structure of spans with visibility information
     * 
     * @return array
     */
    public function getFlattenedHierarchy(): array
    {
        if (!$this->traceModel) {
            return [];
        }
        
        return $this->traceModel->getFlattenedSpanHierarchy();
    }

    /**
     * Get spans by type
     * 
     * @param string $type The span type to filter by
     * @return \Illuminate\Support\Collection
     */
    public function getSpansByType(string $type): \Illuminate\Support\Collection
    {
        if (!$this->traceModel) {
            return collect([]);
        }
        
        $typeMap = [
            'agent' => 'agentSpans',
            'function' => 'functionSpans',
            'tool' => 'functionSpans',
            'handoff' => 'handoffSpans',
            'response' => 'responseSpans',
        ];
        
        $method = $typeMap[$type] ?? null;
        
        if (!$method) {
            return collect([]);
        }
        
        return $this->traceModel->$method()->get();
    }

    /**
     * Get statistics about this trace
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        if (!$this->traceModel) {
            return [
                'duration_ms' => 0,
                'handoff_count' => 0,
                'tool_count' => 0,
                'span_count' => 0,
                'agent_count' => 0,
            ];
        }
        
        // Make sure counts are updated
        $this->traceModel->calculateCounts();
        $this->traceModel->calculateDuration();
        
        return [
            'duration_ms' => $this->traceModel->duration_ms,
            'handoff_count' => $this->traceModel->handoff_count,
            'tool_count' => $this->traceModel->tool_count,
            'span_count' => $this->traceModel->spans()->count(),
            'agent_count' => count($this->traceModel->first_5_agents),
        ];
    }

    /**
     * Add a handoff span between two agents
     * 
     * @param string $fromAgent Name of the source agent
     * @param string $toAgent Name of the target agent
     * @param string|null $parentId Optional parent span ID
     * @param array $metadata Additional metadata
     * @return string The ID of the new span
     */
    public function addHandoff(string $fromAgent, string $toAgent, ?string $parentId = null, array $metadata = []): string
    {
        $handoffMetadata = array_merge($metadata, [
            'from_agent' => $fromAgent,
            'to_agent' => $toAgent,
        ]);
        
        $spanId = $this->startSpan("handoff_{$fromAgent}_to_{$toAgent}", 'handoff', $parentId, $handoffMetadata);
        return $spanId;
    }

    /**
     * Format trace data for API response
     * 
     * @return array
     */
    public function toApiFormat(): array
    {
        if (!$this->traceModel) {
            return [
                'id' => $this->traceId,
                'object' => 'trace',
                'created_at' => now()->toIso8601String(),
            ];
        }
        
        return [
            'id' => $this->traceModel->id,
            'object' => $this->traceModel->object,
            'created_at' => $this->traceModel->created_at->toIso8601String(),
            'duration_ms' => $this->traceModel->duration_ms,
            'workflow_name' => $this->traceModel->workflow_name,
            'group_id' => $this->traceModel->group_id,
            'handoff_count' => $this->traceModel->handoff_count,
            'tool_count' => $this->traceModel->tool_count,
            'metadata' => $this->traceModel->metadata,
        ];
    }

    /**
     * Extract agent metadata from the OpenAI format
     * 
     * @param array $responseData Response data from OpenAI
     * @return array
     */
    protected function extractAgentMetadata(array $responseData): array
    {
        $metadata = [
            'model' => $responseData['meta']['model'] ?? null,
        ];
        
        // Extract usage data if available
        if (!empty($responseData['usage'])) {
            $metadata['usage'] = $responseData['usage'];
        }
        
        // Extract completion ID
        if (!empty($responseData['meta']['id'])) {
            $metadata['completion_id'] = $responseData['meta']['id'];
        }
        
        // Extract rate limits if available
        if (!empty($responseData['meta']['rateLimits'])) {
            $metadata['rate_limits'] = $responseData['meta']['rateLimits'];
        }
        
        return $metadata;
    }

    /**
     * Load agent data from an OpenAI format response
     * 
     * @param array $responseData The OpenAI format response data
     * @param string|null $parentSpanId Optional parent span ID
     * @return string The ID of the agent span
     */
    public function loadAgentData(array $responseData, ?string $parentSpanId = null): string
    {
        $agentName = $responseData['meta']['model'] ?? 'unknown_agent';
        $spanId = $this->startSpan($agentName, 'agent_execution', $parentSpanId);
        
        // Extract metadata
        $metadata = $this->extractAgentMetadata($responseData);
        
        // Extract messages
        $messages = $responseData['messages'] ?? [];
        $spanData = [
            'agent' => $agentName,
            'messages' => $messages,
            'metadata' => $metadata,
        ];
        
        // Add output if available
        if (isset($responseData['text'])) {
            $spanData['output'] = $responseData['text'];
        }
        
        // Update the span with the data
        $this->updateSpan($spanId, $spanData);
        
        // Process steps
        if (!empty($responseData['steps'])) {
            foreach ($responseData['steps'] as $index => $step) {
                $this->processStep($step, $spanId, $index);
            }
        }
        
        return $spanId;
    }

    /**
     * Process a step from the OpenAI response
     * 
     * @param array $step The step data
     * @param string $parentSpanId The parent span ID
     * @param int $index The step index
     * @return string The step span ID
     */
    protected function processStep(array $step, string $parentSpanId, int $index): string
    {
        $stepSpanId = $this->startSpan("step_{$index}", 'llm_step', $parentSpanId);
        
        $this->updateSpan($stepSpanId, [
            'step_index' => $index,
            'text' => $step['text'] ?? '',
            'finish_reason' => $step['finishReason'] ?? null,
            'tool_calls' => $step['toolCalls'] ?? [],
            'usage' => $step['usage'] ?? null,
        ]);
        
        // Process tool results
        if (!empty($step['toolResults'])) {
            foreach ($step['toolResults'] as $toolIdx => $toolResult) {
                $toolCallId = $toolResult['toolCallId'] ?? null;
                $toolName = $toolResult['toolName'] ?? 'unknown_tool';
                
                $spanName = $toolCallId ? 
                    "tool_{$toolName}_{$toolCallId}" : 
                    "tool_{$toolName}_{$toolIdx}";
                
                $toolSpanId = $this->startSpan($spanName, 'tool_call', $stepSpanId);
                
                $this->updateSpan($toolSpanId, [
                    'tool_name' => $toolName,
                    'tool_call_id' => $toolCallId,
                    'args' => $toolResult['args'] ?? [],
                    'result' => $toolResult['result'] ?? null,
                ]);
                
                $this->endSpan($toolSpanId);
            }
        }
        
        $this->endSpan($stepSpanId);
        return $stepSpanId;
    }
} 