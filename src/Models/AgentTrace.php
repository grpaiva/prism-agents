<?php

namespace Grpaiva\PrismAgents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class AgentTrace extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'prism_agent_traces';

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = null;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration' => 'float',
        'tokens_used' => 'integer',
        'step_count' => 'integer',
        'tool_call_count' => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'trace_id',
        'parent_id',
        'name',
        'type',
        'started_at',
        'ended_at',
        'duration',
        'metadata',
        'agent_name',
        'provider',
        'model',
        'input_text',
        'output_text',
        'status',
        'error_message',
        'tokens_used',
        'step_count',
        'tool_call_count',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Set the connection from config if not already set
            if (!$model->getConnectionName()) {
                $connection = Config::get('prism-agents.tracing.connection') ?: config('database.default');
                $model->setConnection($connection);
            }
        });
    }

    /**
     * Get the parent trace.
     */
    public function parent()
    {
        return $this->belongsTo(AgentTrace::class, 'parent_id');
    }

    /**
     * Get the child traces.
     */
    public function children()
    {
        return $this->hasMany(AgentTrace::class, 'parent_id');
    }

    /**
     * Get child handoffs for this trace.
     */
    public function handoffs()
    {
        return $this->children()->where('type', 'handoff');
    }

    /**
     * Get all descendant traces.
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Scope a query to only include root traces (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope a query to only include traces for a specific trace ID.
     */
    public function scopeForTrace($query, $traceId)
    {
        return $query->where('trace_id', $traceId);
    }

    /**
     * Check if the trace has children.
     */
    public function hasChildren()
    {
        return $this->children()->exists();
    }

    /**
     * Check if the trace has handoffs.
     */
    public function hasHandoffs()
    {
        return $this->handoffs()->exists();
    }

    /**
     * Get the count of handoffs for this trace.
     */
    public function getHandoffCountAttribute()
    {
        return $this->handoffs()->count();
    }

    /**
     * Get the trace duration in a formatted string.
     */
    public function getFormattedDurationAttribute()
    {
        if ($this->duration === null) {
            return 'N/A';
        }
        
        // Use absolute value and show with 2 decimal places
        return number_format(abs($this->duration), 2) . ' ms';
    }

    /**
     * Get the actual duration value (absolute).
     */
    public function getActualDurationAttribute()
    {
        return $this->duration ? abs($this->duration) : 0;
    }

    /**
     * Get the status with a default value.
     */
    public function getStatusValueAttribute()
    {
        return $this->status ?? ($this->metadata['status'] ?? 'unknown');
    }

    /**
     * Get a display name for the trace.
     */
    public function getDisplayNameAttribute()
    {
        if ($this->type === 'handoff' && isset($this->metadata['tool_name'])) {
            return $this->metadata['tool_name'];
        }
        
        return $this->name;
    }

    /**
     * Get the step index for llm_step traces.
     */
    public function getStepIndexAttribute()
    {
        if ($this->type === 'llm_step' && isset($this->metadata['step_index'])) {
            return $this->metadata['step_index'];
        }
        
        return null;
    }

    /**
     * Get handoff target agent for handoff traces.
     */
    public function getHandoffTargetAttribute()
    {
        if ($this->type === 'handoff' && isset($this->metadata['tool_name'])) {
            return $this->metadata['tool_name'];
        }
        
        return null;
    }

    /**
     * Build a hierarchical structure for a trace and its descendants.
     *
     * @param string $traceId The ID of the root trace
     * @return array
     */
    public static function buildHierarchy($traceId)
    {
        // Get the root trace
        $rootTrace = self::find($traceId);
        if (!$rootTrace) {
            return [];
        }
        
        // Get all descendants of this trace using a recursive CTE
        $allTraces = self::where(function ($query) use ($traceId) {
                $query->where('id', $traceId)
                      ->orWhere(function ($q) use ($traceId) {
                          // Find traces that are part of this hierarchy by traversing parent_id
                          $rootTrace = self::find($traceId);
                          if ($rootTrace) {
                              $q->where('trace_id', $rootTrace->trace_id)
                                ->whereExists(function ($subquery) use ($traceId) {
                                    $subquery->selectRaw(1)
                                        ->from('prism_agent_traces as t')
                                        ->whereRaw('prism_agent_traces.parent_id = t.id')
                                        ->whereRaw('t.id = ? OR t.parent_id = ?', [$traceId, $traceId]);
                                });
                          }
                      });
            })
            ->orderBy('started_at')
            ->get();
            
        // Build a hierarchy lookup table
        $tracesById = [];
        foreach ($allTraces as $trace) {
            $tracesById[$trace->id] = [
                'model' => $trace,
                'children' => [],
                'level' => 0,
            ];
        }
        
        // Build the hierarchy
        $result = [];
        foreach ($allTraces as $trace) {
            if ($trace->id === $traceId) {
                // Root trace
                $result[] = &$tracesById[$trace->id];
            } else if (isset($tracesById[$trace->parent_id])) {
                // Add as child and set level
                $tracesById[$trace->id]['level'] = $tracesById[$trace->parent_id]['level'] + 1;
                $tracesById[$trace->parent_id]['children'][] = &$tracesById[$trace->id];
            }
        }
        
        // Flatten the hierarchy into a display list with levels
        $flatList = [];
        self::flattenHierarchy($result, $flatList, true); // Root is expanded by default
        
        return $flatList;
    }
    
    /**
     * Helper method to flatten a hierarchical trace structure.
     */
    private static function flattenHierarchy($items, &$result, $parentExpanded = true)
    {
        foreach ($items as $item) {
            // Add the current item
            $item['visible'] = $parentExpanded;
            $result[] = $item;
            
            // Add its children
            if (!empty($item['children'])) {
                self::flattenHierarchy($item['children'], $result, $parentExpanded);
            }
        }
    }
} 