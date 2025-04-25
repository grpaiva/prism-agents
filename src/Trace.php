<?php

namespace Grpaiva\PrismAgents;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Grpaiva\PrismAgents\Models\PrismAgentExecution;
use Grpaiva\PrismAgents\Models\PrismAgentStep;
use Grpaiva\PrismAgents\Models\PrismAgentToolCall;
use Grpaiva\PrismAgents\Models\PrismAgentToolResult;
use Grpaiva\PrismAgents\Models\PrismAgentMessage;

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
     * Current execution being traced
     * 
     * @var PrismAgentExecution|null
     */
    protected ?PrismAgentExecution $currentExecution = null;

    /**
     * Current step being traced
     * 
     * @var PrismAgentStep|null
     */
    protected ?PrismAgentStep $currentStep = null;

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
        
        // Log the current tracing configuration for debugging
        \Illuminate\Support\Facades\Log::debug('Trace configuration', [
            'trace_id' => $this->traceId,
            'enabled' => $this->enabled,
            'connection' => $this->connection
        ]);
        
        // Verify table existence
        $this->verifyTraceTables();
    }

    /**
     * Verify if the required trace tables exist in the database
     * 
     * @return bool
     */
    protected function verifyTraceTables(): bool
    {
        try {
            if (!$this->enabled) {
                return false;
            }
            
            // Check if the required tables exist
            $schema = Schema::connection($this->connection);
            
            $requiredTables = [
                'prism_agent_executions',
                'prism_agent_steps',
                'prism_agent_tool_calls',
                'prism_agent_tool_results',
                'prism_agent_messages'
            ];
            
            $missingTables = [];
            foreach ($requiredTables as $table) {
                if (!$schema->hasTable($table)) {
                    $missingTables[] = $table;
                }
            }
            
            if (!empty($missingTables)) {
                \Illuminate\Support\Facades\Log::warning("Tracing tables do not exist: " . implode(', ', $missingTables) . ". Please run migrations.", [
                    'connection' => $this->connection,
                    'missing_tables' => $missingTables
                ]);
                
                // Disable tracing if tables don't exist
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
     * Start a new execution span
     *
     * @param string $name
     * @param array $metadata
     * @return string The execution ID
     */
    public function startExecution(string $name, array $metadata = []): string
    {
        if (!$this->enabled) {
            return Str::uuid()->toString();
        }

        $executionId = Str::uuid()->toString();

        $execution = new PrismAgentExecution([
            'id' => $executionId,
            'name' => $name,
            'type' => 'execution',
            'status' => 'running',
                'provider' => $metadata['provider'] ?? null,
                'model' => $metadata['model'] ?? null,
            'meta' => $metadata,
            'started_at' => now(),
        ]);

        if (isset($metadata['user_id'])) {
            $execution->user_id = $metadata['user_id'];
        }
        
        if (isset($metadata['parent_id'])) {
            $execution->parent_id = $metadata['parent_id'];
        }

        $execution->save();
        $this->currentExecution = $execution;

        return $executionId;
    }

    /**
     * Start a new step span
     *
     * @param string $name
     * @param array $metadata
     * @return string The step ID
     */
    public function startStep(string $name, array $metadata = []): string
    {
        if (!$this->enabled || !$this->currentExecution) {
            return Str::uuid()->toString();
                        }
                        
        $stepId = Str::uuid()->toString();

        $step = new PrismAgentStep([
            'id' => $stepId,
            'execution_id' => $this->currentExecution->id,
            'step_index' => $this->currentExecution->steps()->count(),
            'text' => $metadata['text'] ?? null,
            'finish_reason' => $metadata['finish_reason'] ?? null,
            'usage' => $metadata['usage'] ?? null,
            'meta' => $metadata,
            'started_at' => now(),
        ]);

        $step->save();
        $this->currentStep = $step;

        return $stepId;
    }

    /**
     * Start a new tool call span
     *
     * @param string $name
     * @param array $metadata
     * @return string The tool call ID
     */
    public function startToolCall(string $name, array $metadata = []): string
    {
        if (!$this->enabled || !$this->currentStep) {
            return Str::uuid()->toString();
        }

        $toolCallId = Str::uuid()->toString();
        
        $toolCall = new PrismAgentToolCall([
            'id' => $toolCallId,
            'step_id' => $this->currentStep->id,
            'call_id' => $metadata['call_id'] ?? Str::uuid()->toString(),
            'name' => $name,
            'args' => $metadata['args'] ?? null,
            'started_at' => now(),
        ]);

        $toolCall->save();
        return $toolCallId;
    }

    /**
     * Record a tool result
     *
     * @param string $toolCallId
     * @param array $metadata
     * @return void
     */
    public function recordToolResult(string $toolCallId, array $metadata = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $resultId = Str::uuid()->toString();

        $toolResult = new PrismAgentToolResult([
            'id' => $resultId,
            'tool_call_id' => $toolCallId,
            'tool_name' => $metadata['tool_name'] ?? 'unknown',
            'args' => $metadata['args'] ?? null,
            'result' => $metadata['result'] ?? null,
            'created_at' => now(),
        ]);
        
        $toolResult->save();
    }

    /**
     * Record a message
     *
     * @param array $metadata
     * @return void
     */
    public function recordMessage(array $metadata = []): void
    {
        if (!$this->enabled || !$this->currentStep) {
            return;
        }

        $messageId = Str::uuid()->toString();

        $message = new PrismAgentMessage([
            'id' => $messageId,
            'step_id' => $this->currentStep->id,
            'content' => $metadata['content'] ?? null,
            'tool_calls' => $metadata['tool_calls'] ?? null,
            'additional_content' => $metadata['additional_content'] ?? null,
            'message_index' => $this->currentStep->messages()->count(),
            'created_at' => now(),
        ]);

        $message->save();
    }

    /**
     * End the current execution
     *
     * @param array $metadata
     * @return void
     */
    public function endExecution(array $metadata = []): void
    {
        if (!$this->enabled || !$this->currentExecution) {
            return;
        }

        $this->currentExecution->update([
            'status' => $metadata['status'] ?? 'completed',
            'error_message' => $metadata['error_message'] ?? null,
            'total_tokens' => $metadata['total_tokens'] ?? null,
            'prompt_tokens' => $metadata['prompt_tokens'] ?? null,
            'completion_tokens' => $metadata['completion_tokens'] ?? null,
            'ended_at' => now(),
        ]);

        $this->currentExecution->calculateDuration();
        $this->currentExecution = null;
    }

    /**
     * End the current step
     *
     * @return void
     */
    public function endStep(): void
    {
        if (!$this->enabled || !$this->currentStep) {
            return;
        }

        $this->currentStep->update([
            'ended_at' => now(),
        ]);

        $this->currentStep->calculateDuration();
        $this->currentStep = null;
    }

    /**
     * End a tool call
     *
     * @param string $toolCallId
     * @param array $metadata
     * @return void
     */
    public function endToolCall(string $toolCallId, array $metadata = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $toolCall = PrismAgentToolCall::find($toolCallId);
        if ($toolCall) {
            $toolCall->update([
                'ended_at' => now(),
            ]);
            $toolCall->calculateDuration();
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
        
        try {
            $agent = $result->getAgent();
            $metadata = $result->getMetadata() ?? [];

            // Start execution
            $executionId = $this->startExecution(
                $agent ? $agent->getName() : 'unknown_agent',
                [
                    'provider' => $metadata['provider'] ?? null,
                    'model' => $metadata['model'] ?? null,
                    'user_id' => $metadata['user_id'] ?? null,
                    'parent_id' => $metadata['parent_id'] ?? null,
                ]
            );

            // Process steps
            $steps = $result->getSteps();
            
            // Debug log for steps structure
            Log::debug('Steps structure', [
                'steps_count' => count($steps),
                'steps_type' => gettype($steps),
                'first_step_type' => empty($steps) ? 'none' : gettype($steps[0]),
            ]);
            
            if (!empty($steps)) {
                foreach ($steps as $stepIndex => $step) {
                    // Debug log step structure
                    Log::debug('Processing step', [
                        'step_index' => $stepIndex,
                        'step_type' => gettype($step),
                        'step_keys' => is_array($step) ? array_keys($step) : 'not_array',
                    ]);
                    
                    $stepId = $this->startStep('step', [
                        'text' => is_array($step) && isset($step['text']) ? $step['text'] : 
                               (is_object($step) && property_exists($step, 'text') ? $step->text : null),
                        'finish_reason' => is_array($step) && isset($step['finish_reason']) ? $step['finish_reason'] : 
                                       (is_object($step) && property_exists($step, 'finish_reason') ? $step->finish_reason : 
                                       (is_object($step) && property_exists($step, 'finishReason') ? $step->finishReason : null)),
                    ]);

                    // Extract tool calls safely
                    $toolCalls = [];
                    if (is_array($step) && isset($step['tool_calls'])) {
                        $toolCalls = $step['tool_calls'];
                    } elseif (is_object($step) && property_exists($step, 'tool_calls')) {
                        $toolCalls = $step->tool_calls;
                    } elseif (is_object($step) && property_exists($step, 'toolCalls')) {
                        $toolCalls = $step->toolCalls;
                    }

                    // Process tool calls
                    if (!empty($toolCalls)) {
                        // Debug log tool calls structure
                        Log::debug('Tool calls structure', [
                            'count' => count($toolCalls),
                            'type' => gettype($toolCalls),
                            'first_tool_call_type' => empty($toolCalls) ? 'none' : gettype($toolCalls[0]),
                        ]);
                        
                        foreach ($toolCalls as $toolCallIndex => $toolCall) {
                            // Debug log for individual tool call
                            Log::debug('Processing tool call', [
                                'tool_call_index' => $toolCallIndex,
                                'tool_call_type' => gettype($toolCall),
                                'tool_call_class' => is_object($toolCall) ? get_class($toolCall) : 'not_object',
                                'tool_call_props' => is_object($toolCall) ? get_object_vars($toolCall) : 'not_object',
                            ]);
                            
                            // Extract name and ID safely
                            $toolCallName = 'unknown';
                            $toolCallId = Str::uuid()->toString();
                            $toolCallArgs = null;
                            
                            if (is_array($toolCall)) {
                                $toolCallName = $toolCall['name'] ?? 'unknown';
                                $toolCallId = $toolCall['id'] ?? Str::uuid()->toString();
                                $toolCallArgs = $toolCall['args'] ?? null;
                            } elseif (is_object($toolCall)) {
                                $toolCallName = property_exists($toolCall, 'name') ? $toolCall->name : 'unknown';
                                $toolCallId = property_exists($toolCall, 'id') ? $toolCall->id : Str::uuid()->toString();
                                $toolCallArgs = property_exists($toolCall, 'args') ? $toolCall->args : null;
                            }
                            
                            // Record the tool call
                            $dbToolCallId = $this->startToolCall($toolCallName, [
                                'call_id' => $toolCallId,
                                'args' => $toolCallArgs,
                            ]);
                            
                            // Extract tool results safely
                            $toolResults = [];
                            if (is_array($step) && isset($step['tool_results'])) {
                                $toolResults = $step['tool_results'];
                            } elseif (is_object($step) && property_exists($step, 'tool_results')) {
                                $toolResults = $step->tool_results;
                            } elseif (is_object($step) && property_exists($step, 'toolResults')) {
                                $toolResults = $step->toolResults;
                            }
                            
                            // Debug log tool results structure
                            if (!empty($toolResults)) {
                                Log::debug('Tool results structure', [
                                    'count' => count($toolResults),
                                    'type' => gettype($toolResults),
                                    'first_result_type' => empty($toolResults) ? 'none' : gettype($toolResults[0]),
                                ]);
                            }
                            
                            // Process matching tool results
                            if (!empty($toolResults)) {
                                foreach ($toolResults as $toolResult) {
                                    $resultToolCallId = null;
                                    $resultToolName = 'unknown';
                                    $resultArgs = null;
                                    $resultResult = null;
                                    
                                    if (is_array($toolResult)) {
                                        $resultToolCallId = $toolResult['toolCallId'] ?? null;
                                        $resultToolName = $toolResult['toolName'] ?? 'unknown';
                                        $resultArgs = $toolResult['args'] ?? null;
                                        $resultResult = $toolResult['result'] ?? null;
                                    } elseif (is_object($toolResult)) {
                                        $resultToolCallId = property_exists($toolResult, 'toolCallId') ? $toolResult->toolCallId : null;
                                        $resultToolName = property_exists($toolResult, 'toolName') ? $toolResult->toolName : 'unknown';
                                        $resultArgs = property_exists($toolResult, 'args') ? $toolResult->args : null;
                                        $resultResult = property_exists($toolResult, 'result') ? $toolResult->result : null;
                                    }
                                    
                                    // Only record results for the current tool call
                                    if ($resultToolCallId === $toolCallId) {
                                        $this->recordToolResult($dbToolCallId, [
                                            'tool_name' => $resultToolName,
                                            'args' => $resultArgs,
                                            'result' => $resultResult,
                                        ]);
                                        break;
                                    }
                                }
                            }
                            
                            $this->endToolCall($dbToolCallId);
                        }
                    }

                    // Extract messages safely
                    $messages = [];
                    if (is_array($step) && isset($step['messages'])) {
                        $messages = $step['messages'];
                    } elseif (is_object($step) && property_exists($step, 'messages')) {
                        $messages = $step->messages;
                    }
                    
                    // Process messages
                    if (!empty($messages)) {
                        foreach ($messages as $messageIndex => $message) {
                            $content = null;
                            $toolCalls = null;
                            $additionalContent = null;
                            
                            if (is_array($message)) {
                                $content = $message['content'] ?? null;
                                $toolCalls = $message['tool_calls'] ?? null;
                                $additionalContent = $message['additional_content'] ?? null;
                            } elseif (is_object($message)) {
                                $content = property_exists($message, 'content') ? $message->content : null;
                                $toolCalls = property_exists($message, 'toolCalls') ? $message->toolCalls : null;
                                $additionalContent = property_exists($message, 'additionalContent') ? $message->additionalContent : null;
        }
        
                            $this->recordMessage([
                                'content' => $content,
                                'tool_calls' => $toolCalls,
                                'additional_content' => $additionalContent,
                            ]);
                        }
                    }
                    
                    // End the step
                    $this->endStep();
                }
            }

            // End execution
            $this->endExecution([
                'status' => $result->isSuccess() ? 'completed' : 'failed',
                'error_message' => $result->getError(),
                'total_tokens' => $metadata['usage']['total_tokens'] ?? null,
                'prompt_tokens' => $metadata['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $metadata['usage']['completion_tokens'] ?? null,
            ]);

            return $this;
        } catch (\Exception $e) {
            Log::error('Error in addResult: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Do not rethrow, just return
        return $this;
        }
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