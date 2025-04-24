<?php

namespace Grpaiva\PrismAgents\Tracing;

use Grpaiva\PrismAgents\Models\AgentExecution;
use Grpaiva\PrismAgents\Models\AgentSpan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Throwable;

class Tracer
{
    protected ?AgentExecution $currentExecution = null;
    protected array $activeSpanStack = [];
    protected bool $enabled;
    protected ?string $connection;

    public function __construct(?string $executionId = null, ?string $workflowName = 'Unnamed Workflow', ?string $groupId = null, array $metadata = [])
    {
        $this->enabled = Config::get('prism-agents.tracing.enabled', true);
        $this->connection = Config::get('prism-agents.tracing.connection') ?: config('database.default');

        if ($this->enabled) {
            $this->startExecution($executionId, $workflowName, $groupId, $metadata);
        }
    }

    /**
     * Start a new execution trace.
     */
    public function startExecution(?string $id = null, string $workflowName, ?string $groupId = null, array $metadata = []): ?AgentExecution
    {
        if (!$this->enabled) {
            return null;
        }

        $this->currentExecution = AgentExecution::create([
            'id' => $id ?: (string) Str::uuid(),
            'workflow_name' => $workflowName,
            'group_id' => $groupId,
            'metadata' => $metadata,
            'started_at' => now(),
            'status' => 'running',
        ]);
        
        $this->activeSpanStack = []; // Reset span stack for new execution

        return $this->currentExecution;
    }

    /**
     * End the current execution trace.
     */
    public function endExecution(string $status = 'completed', ?Throwable $error = null): void
    {
        if (!$this->enabled || !$this->currentExecution) {
            return;
        }

        $this->currentExecution->ended_at = now();
        $this->currentExecution->duration_ms = $this->calculateDurationMs($this->currentExecution->started_at, $this->currentExecution->ended_at);
        $this->currentExecution->status = $error ? 'failed' : $status;
        
        // TODO: Calculate aggregate handoff/tool counts before saving?
        // Or maybe this is better done via listeners or queries later.
        // For now, leave counts as default 0.

        $this->currentExecution->save();
        
        // Clear state
        $this->currentExecution = null;
        $this->activeSpanStack = [];
    }
    
    /**
     * Get the current execution ID.
     */
    public function getExecutionId(): ?string
    {
        return $this->currentExecution?->id;
    }

    /**
     * Start a new span within the current execution.
     */
    public function startSpan(string $name, string $type, array $spanData = [], ?string $parentSpanId = null): ?AgentSpan
    {
        if (!$this->enabled || !$this->currentExecution) {
            return null;
        }

        // Determine parent from stack if not explicitly provided
        if ($parentSpanId === null && !empty($this->activeSpanStack)) {
            $parentSpanId = end($this->activeSpanStack);
        }

        $span = AgentSpan::create([
            'id' => (string) Str::uuid(),
            'execution_id' => $this->currentExecution->id,
            'parent_span_id' => $parentSpanId,
            'name' => $name,
            'type' => $type,
            'status' => 'running',
            'started_at' => now(),
            'span_data' => $spanData, // Already handles JSON encoding in model boot
        ]);

        // Push the new span ID onto the stack
        $this->activeSpanStack[] = $span->id;

        return $span;
    }

    /**
     * End the currently active span (or a specific span ID).
     */
    public function endSpan(?string $spanId = null, string $status = 'success', array $spanDataUpdate = [], ?Throwable $error = null): ?AgentSpan
    {
        if (!$this->enabled || !$this->currentExecution) {
            return null;
        }

        // If no spanId provided, end the last one on the stack
        if ($spanId === null) {
            if (empty($this->activeSpanStack)) {
                // Cannot end a span if the stack is empty
                return null;
            }
            $spanId = array_pop($this->activeSpanStack);
        } else {
            // If a specific spanId is provided, remove it from the stack if present
            $key = array_search($spanId, $this->activeSpanStack);
            if ($key !== false) {
                unset($this->activeSpanStack[$key]);
                // Re-index array if needed, though usually popping last is safer
                $this->activeSpanStack = array_values($this->activeSpanStack); 
            }
        }

        $span = AgentSpan::find($spanId);
        if (!$span || $span->execution_id !== $this->currentExecution->id) {
            // Span not found or belongs to a different execution
            return null; 
        }

        $span->ended_at = now();
        $span->duration_ms = $this->calculateDurationMs($span->started_at, $span->ended_at);
        $span->status = $error ? 'error' : $status;
        
        // Merge new span data with existing
        if (!empty($spanDataUpdate)) {
            $currentData = is_array($span->span_data) ? $span->span_data : (json_decode($span->span_data, true) ?? []);
            $span->span_data = array_merge($currentData, $spanDataUpdate);
        }
        
        // Store error information if provided
        if ($error) {
            $span->error = [
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                // Consider adding stack trace if needed, but be mindful of size
                // 'trace' => $error->getTraceAsString(), 
            ];
        }

        $span->save();
        
        // Potentially update execution counts here if desired
        if ($span->type === 'handoff') {
           // $this->currentExecution->increment('handoff_count'); // Requires DB call
        } elseif ($span->type === 'tool_call') {
           // $this->currentExecution->increment('tool_call_count'); // Requires DB call
        }


        return $span;
    }
    
    /**
     * Add an event/log to the currently active span.
     * This is useful for adding specific log points within a span's lifetime.
     */
     public function addEvent(string $eventName, array $attributes = [], ?string $spanId = null): void
     {
         if (!$this->enabled || !$this->currentExecution) {
             return;
         }
         
         // If no spanId provided, use the last one on the stack
         if ($spanId === null) {
             if (empty($this->activeSpanStack)) {
                 return; // No active span to add event to
             }
             $spanId = end($this->activeSpanStack);
         }
         
         $span = AgentSpan::find($spanId);
         if (!$span || $span->execution_id !== $this->currentExecution->id) {
             return; // Span not found or invalid
         }
         
         $eventData = [
             'name' => $eventName,
             'timestamp' => now()->toISOString(),
             'attributes' => $attributes,
         ];
         
         // Append the event to the span_data
         $currentData = is_array($span->span_data) ? $span->span_data : (json_decode($span->span_data, true) ?? []);
         if (!isset($currentData['events'])) {
             $currentData['events'] = [];
         }
         $currentData['events'][] = $eventData;
         
         $span->span_data = $currentData;
         $span->save();
     }

    /**
     * Calculate duration in milliseconds.
     */
    protected function calculateDurationMs(Carbon $start, Carbon $end): int
    {
        return (int) round($start->diffInMicroseconds($end) / 1000);
    }
    
    /**
     * Check if tracing is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
} 