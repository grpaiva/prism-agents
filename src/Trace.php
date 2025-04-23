<?php

namespace Grpaiva\PrismAgents;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

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
     * The table name to store traces in
     *
     * @var string
     */
    protected string $table;

    /**
     * Optional trace name
     * 
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * Protected constructor to enforce use of static factory methods
     *
     * @param string|null $traceId
     */
    protected function __construct(?string $traceId = null)
    {
        $this->traceId = $traceId ?? Str::uuid()->toString();
        
        // Load configuration
        $this->enabled = Config::get('prism-agents.tracing.enabled', true);
        
        // If no specific connection is provided, use the default database connection
        $this->connection = Config::get('prism-agents.tracing.connection') ?: config('database.default');
        
        $this->table = Config::get('prism-agents.tracing.table', 'prism_agent_traces');
        
        // Log the current tracing configuration for debugging
        \Illuminate\Support\Facades\Log::debug('Trace configuration', [
            'trace_id' => $this->traceId,
            'enabled' => $this->enabled,
            'connection' => $this->connection,
            'table' => $this->table
        ]);
        
        // Verify table existence
        $this->verifyTraceTable();
    }

    /**
     * Verify if the trace table exists in the database
     * 
     * @return bool
     */
    protected function verifyTraceTable(): bool
    {
        try {
            if (!$this->enabled) {
                return false;
            }
            
            // Check if the table exists
            $schema = DB::connection($this->connection)->getSchemaBuilder();
            
            $tableExists = $schema->hasTable($this->table);
            
            if (!$tableExists) {
                \Illuminate\Support\Facades\Log::warning("Tracing table '{$this->table}' does not exist. Please run migrations.", [
                    'connection' => $this->connection,
                    'table' => $this->table
                ]);
                
                // Disable tracing if the table doesn't exist
                $this->enabled = false;
                return false;
            }
            
            // For more detailed verification, we could check the columns too:
            /*
            $columns = $schema->getColumnListing($this->table);
            $requiredColumns = ['id', 'trace_id', 'parent_id', 'name', 'type', 'started_at', 'ended_at', 'metadata'];
            
            $missingColumns = array_diff($requiredColumns, $columns);
            if (!empty($missingColumns)) {
                \Illuminate\Support\Facades\Log::warning("Tracing table '{$this->table}' is missing columns: " . implode(', ', $missingColumns), [
                    'connection' => $this->connection,
                    'table' => $this->table,
                    'existing_columns' => $columns,
                    'required_columns' => $requiredColumns
                ]);
                
                // Don't disable tracing but log the issue
            }
            */
            
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error verifying trace table: " . $e->getMessage(), [
                'connection' => $this->connection,
                'table' => $this->table,
                'exception' => $e
            ]);
            
            // Disable tracing on error
            $this->enabled = false;
            return false;
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
            $instance->traceId = $name; // Use the name as the trace ID for easier retrieval
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
        $instance = new static($nameOrId);
        
        // Attempt to load spans from the database based on the provided ID
        // This is simplified - in a real implementation, you'd load spans from DB
        try {
            $spans = DB::connection($instance->connection)
                ->table($instance->table)
                ->where('trace_id', $nameOrId)
                ->orderBy('started_at')
                ->get()
                ->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'trace_id' => $row->trace_id,
                        'parent_id' => $row->parent_id,
                        'name' => $row->name,
                        'type' => $row->type,
                        'started_at' => new Carbon($row->started_at),
                        'ended_at' => $row->ended_at ? new Carbon($row->ended_at) : null,
                        'duration' => $row->duration,
                        'metadata' => json_decode($row->metadata, true) ?? [],
                    ];
                })
                ->toArray();
                
            if (empty($spans)) {
                return null;
            }
            
            // Populate the instance with the loaded spans
            foreach ($spans as $span) {
                $instance->spans[$span['id']] = $span;
            }
            
            return $instance;
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
        // Get information from the result
        $agent = $result->getAgent();
        $metadata = $result->getMetadata() ?? [];
        
        // Extract system message information
        $systemMessage = $metadata['system_message'] ?? null;
        
        // Start a span for the agent execution
        $spanId = $this->startSpan(
            $agent ? $agent->getName() : 'unknown_agent',
            'agent_execution',
            [
                'agent' => $agent ? $agent->getName() : 'unknown',
                'provider' => $metadata['provider'] ?? null,
                'model' => $metadata['model'] ?? null,
                'input' => $result->getInput(),
                'metadata' => $metadata,
                'steps' => $result->getSteps(),
                'tool_calls' => $result->getToolResults(),
                'system_message' => $systemMessage,
            ]
        );
        
        // Store agent tools for later use in identifying handoffs
        $agentTools = [];
        if (isset($metadata['tools']) && is_array($metadata['tools'])) {
            $agentTools = array_map(function($tool) {
                return $tool['name'] ?? null;
            }, $metadata['tools']);
            $agentTools = array_filter($agentTools);
        }
        
        // Add spans for each step if available
        $steps = $result->getSteps();
        if (!empty($steps)) {
            $stepIndex = 0;
            foreach ($steps as $step) {
                $stepSpanId = $this->startSpan(
                    "step_" . $stepIndex,
                    'llm_step',
                    [
                        'step_index' => $stepIndex,
                        'agent' => $agent ? $agent->getName() : 'unknown',
                        'text' => $step['text'] ?? '',
                        'finish_reason' => $step['finish_reason'] ?? null,
                        'tools' => !empty($step['tool_calls']) ? array_map(fn($tc) => $tc->name ?? $tc->toolName ?? 'unknown', $step['tool_calls']) : [],
                        'additional_content' => $step['additional_content'] ?? [],
                    ]
                );
                
                // If there are tool results in this step, create subspans for them
                if (!empty($step['tool_results'])) {
                    foreach ($step['tool_results'] as $toolIdx => $toolResult) {
                        $toolName = $toolResult->toolName ?? 'unknown_tool';
                        
                        // Determine if this is an agent-as-tool call (handoff)
                        // We can check this based on tool name matching an agent name pattern
                        // or by checking if the metadata contains agent-specific data
                        $isAgentTool = false;
                        
                        // Check if the tool name matches an agent name from our pre-processed list
                        if (!empty($agentTools)) {
                            $isAgentTool = in_array($toolName, $agentTools);
                        }
                        
                        // Default to checking if the tool name matches an agent name pattern
                        // This is a heuristic approach since we don't have direct agent reference
                        if (!$isAgentTool) {
                            $isAgentTool = str_contains($toolName, '_agent') || 
                                           str_contains($toolName, 'Agent') || 
                                           isset($toolResult->result) && (
                                               is_string($toolResult->result) && 
                                               strlen($toolResult->result) > 20
                                           );
                        }
                        
                        $spanType = $isAgentTool ? 'handoff' : 'tool_call';
                        
                        $toolSpanId = $this->startSpan(
                            "tool_" . $toolName . "_" . $toolIdx,
                            $spanType,
                            [
                                'tool_name' => $toolName,
                                'args' => $toolResult->args ?? [],
                                'result' => $toolResult->result ?? null,
                            ]
                        );
                        $this->endSpan($toolSpanId);
                    }
                }
                
                $this->endSpan($stepSpanId);
                $stepIndex++;
            }
        }

        // End the main agent execution span
        $this->endSpan($spanId, [
            'output' => $result->getOutput(),
            'status' => $result->isSuccess() ? 'success' : 'error',
            'error' => $result->getError(),
            'metadata' => $result->getMetadata(),
        ]);

        return $this;
    }

    /**
     * Start a new span
     *
     * @param string $name
     * @param string $type
     * @param array $metadata
     * @return string The span ID
     */
    public function startSpan(string $name, string $type, array $metadata = []): string
    {
        $spanId = Str::uuid()->toString();
        $parentSpanId = empty($this->activeSpans) ? null : end($this->activeSpans);
        
        $span = [
            'id' => $spanId,
            'trace_id' => $this->traceId,
            'parent_id' => $parentSpanId,
            'name' => $name,
            'type' => $type,
            'started_at' => Carbon::now(),
            'metadata' => $metadata,
        ];
        
        $this->spans[$spanId] = $span;
        $this->activeSpans[] = $spanId;
        
        if ($this->enabled) {
            $this->saveSpan($span);
        }
        
        return $spanId;
    }

    /**
     * End a span
     *
     * @param string $spanId
     * @param array $metadata
     * @return $this
     */
    public function endSpan(string $spanId, array $metadata = []): self
    {
        if (!isset($this->spans[$spanId])) {
            return $this;
        }
        
        $span = &$this->spans[$spanId];
        $span['ended_at'] = Carbon::now();
        $span['duration'] = $span['ended_at']->diffInMilliseconds($span['started_at']);
        $span['metadata'] = array_merge($span['metadata'] ?? [], $metadata);
        
        // Remove from active spans
        $index = array_search($spanId, $this->activeSpans);
        if ($index !== false) {
            array_splice($this->activeSpans, $index, 1);
        }
        
        if ($this->enabled) {
            $this->updateSpan($span);
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
        return $this;
    }

    /**
     * Configure table name
     * 
     * @param string $table
     * @return $this
     */
    public function withTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Save a span to the database
     *
     * @param array $span
     * @return void
     */
    protected function saveSpan(array $span): void
    {
        if (!$this->enabled) {
            return;
        }
        
        try {
            // Check which columns exist in the table
            $schema = DB::connection($this->connection)->getSchemaBuilder();
            $columns = $schema->getColumnListing($this->table);
            
            $metadata = $span['metadata'] ?? [];
            
            // Start with essential columns that should always exist
            $data = [
                'id' => $span['id'],
                'trace_id' => $span['trace_id'],
                'parent_id' => $span['parent_id'],
                'name' => $span['name'],
                'type' => $span['type'],
                'metadata' => json_encode($metadata),
            ];
            
            // For SQLite, ensure timestamps are formatted as strings
            if (in_array('started_at', $columns)) {
                $data['started_at'] = $span['started_at'] instanceof Carbon 
                    ? $span['started_at']->toDateTimeString() 
                    : $span['started_at'];
            }
            
            if (in_array('ended_at', $columns) && isset($span['ended_at'])) {
                $data['ended_at'] = $span['ended_at'] instanceof Carbon 
                    ? $span['ended_at']->toDateTimeString() 
                    : $span['ended_at'];
            }
            
            if (in_array('duration', $columns) && isset($span['duration'])) {
                $data['duration'] = $span['duration'];
            }
            
            // Add standard Laravel timestamps if they exist
            if (in_array('created_at', $columns)) {
                $data['created_at'] = Carbon::now()->toDateTimeString();
            }
            
            if (in_array('updated_at', $columns)) {
                $data['updated_at'] = Carbon::now()->toDateTimeString();
            }
            
            // Only add extended columns if they exist in the table
            $extendedColumns = [
                'agent_name' => $metadata['agent'] ?? null,
                'provider' => $metadata['provider'] ?? null,
                'model' => $metadata['model'] ?? null,
                'input_text' => $this->truncateText($metadata['input'] ?? null),
                'output_text' => $this->truncateText($metadata['output'] ?? null),
                'status' => $metadata['status'] ?? null,
                'error_message' => $this->truncateText($metadata['error'] ?? null),
                'tokens_used' => $metadata['metadata']['usage']['total_tokens'] ?? null,
                'step_count' => isset($metadata['steps']) ? count($metadata['steps']) : null,
                'tool_call_count' => isset($metadata['tool_calls']) ? count($metadata['tool_calls']) : null,
            ];
            
            foreach ($extendedColumns as $column => $value) {
                if (in_array($column, $columns)) {
                    $data[$column] = $value;
                }
            }
            
            // Log data for debugging
            \Illuminate\Support\Facades\Log::debug('Saving span to database', [
                'trace_id' => $span['trace_id'],
                'span_id' => $span['id'],
                'table' => $this->table,
                'connection' => $this->connection,
                'columns' => $columns,
                'data_keys' => array_keys($data)
            ]);
            
            // Wrap in a transaction to ensure data consistency
            $result = DB::connection($this->connection)->transaction(function () use ($data) {
                return DB::connection($this->connection)->table($this->table)->insert($data);
            });
            
            if (!$result) {
                \Illuminate\Support\Facades\Log::error('Failed to insert span', [
                    'trace_id' => $span['trace_id'],
                    'span_id' => $span['id'],
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error saving span: ' . $e->getMessage(), [
                'trace_id' => $span['trace_id'] ?? null,
                'span_id' => $span['id'] ?? null,
                'exception' => $e
            ]);
        }
    }

    /**
     * Update a span in the database
     *
     * @param array $span
     * @return void
     */
    protected function updateSpan(array $span): void
    {
        if (!$this->enabled) {
            return;
        }
        
        try {
            // Check which columns exist in the table
            $schema = DB::connection($this->connection)->getSchemaBuilder();
            $columns = $schema->getColumnListing($this->table);
            
            $metadata = $span['metadata'] ?? [];
            
            // Start with essential columns for updates
            $data = [
                'metadata' => json_encode($metadata),
            ];
            
            // For SQLite, ensure timestamps are formatted as strings
            if (in_array('ended_at', $columns) && isset($span['ended_at'])) {
                $data['ended_at'] = $span['ended_at'] instanceof Carbon 
                    ? $span['ended_at']->toDateTimeString() 
                    : $span['ended_at'];
            }
            
            if (in_array('duration', $columns) && isset($span['duration'])) {
                $data['duration'] = $span['duration'];
            }
            
            // Add updated_at if it exists
            if (in_array('updated_at', $columns)) {
                $data['updated_at'] = Carbon::now()->toDateTimeString();
            }
            
            // Only add extended columns if they exist in the table
            $extendedColumns = [
                'output_text' => $this->truncateText($metadata['output'] ?? null),
                'status' => $metadata['status'] ?? null,
                'error_message' => $this->truncateText($metadata['error'] ?? null),
                'tokens_used' => $metadata['metadata']['usage']['total_tokens'] ?? null,
                'step_count' => isset($metadata['steps']) ? count($metadata['steps']) : null,
                'tool_call_count' => isset($metadata['tool_calls']) ? count($metadata['tool_calls']) : null,
            ];
            
            foreach ($extendedColumns as $column => $value) {
                if (in_array($column, $columns)) {
                    $data[$column] = $value;
                }
            }
            
            // Log data for debugging
            \Illuminate\Support\Facades\Log::debug('Updating span in database', [
                'trace_id' => $span['trace_id'],
                'span_id' => $span['id'],
                'table' => $this->table,
                'connection' => $this->connection,
                'columns' => $columns,
                'data_keys' => array_keys($data)
            ]);
            
            // Only proceed if we have data to update
            if (empty($data)) {
                \Illuminate\Support\Facades\Log::warning('No updateable columns found for span', [
                    'trace_id' => $span['trace_id'],
                    'span_id' => $span['id'],
                ]);
                return;
            }
            
            // Wrap in a transaction to ensure data consistency
            $result = DB::connection($this->connection)->transaction(function () use ($span, $data) {
                return DB::connection($this->connection)->table($this->table)
                    ->where('id', $span['id'])
                    ->update($data);
            });
            
            if ($result === 0) {
                \Illuminate\Support\Facades\Log::warning('No rows updated for span', [
                    'trace_id' => $span['trace_id'],
                    'span_id' => $span['id'],
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating span: ' . $e->getMessage(), [
                'trace_id' => $span['trace_id'] ?? null,
                'span_id' => $span['id'] ?? null,
                'exception' => $e
            ]);
        }
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
        return $this->name;
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
     * Get the current active span ID
     *
     * @return string|null
     */
    public function getCurrentSpanId(): ?string
    {
        return empty($this->activeSpans) ? null : end($this->activeSpans);
    }
} 