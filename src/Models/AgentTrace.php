<?php

namespace Grpaiva\PrismAgents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

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
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'formatted_duration',
        'status_value',
    ];

    /**
     * Default attribute values.
     * 
     * @var array
     */
    protected $attributes = [
        'started_at' => null,
    ];

    /**
     * Create a new model instance.
     *
     * @param array $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        // Set the connection from config immediately
        if (!$this->getConnectionName()) {
            $connection = Config::get('prism-agents.tracing.connection') ?: config('database.default');
            $this->setConnection($connection);
        }

        // Set default timestamp for started_at if not provided
        if (!isset($attributes['started_at'])) {
            $attributes['started_at'] = now();
        }

        parent::__construct($attributes);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Ensure started_at is set when creating
            if (!$model->started_at) {
                $model->started_at = now();
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
     * Get tool calls for this trace.
     */
    public function toolCalls()
    {
        return $this->children()->where('type', 'tool_call');
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
        // If this is a root trace, count all handoffs in this trace hierarchy
        if ($this->parent_id === null) {
            $tableName = $this->getTable();
            $connection = $this->getConnectionName();
            
            // Use a cached value if it exists in the handoff_count column
            if (isset($this->attributes['handoff_count']) && $this->attributes['handoff_count'] !== null) {
                return (int) $this->attributes['handoff_count'];
            }
            
            // Use a recursive query to find all descendants of this trace
            $descendants = $this->getAllDescendantIds();
            
            // Count handoffs among descendants
            return DB::connection($connection)->table($tableName)
                ->whereIn('id', $descendants)
                ->where('type', 'handoff')
                ->count();
        }
        
        // Otherwise just count direct handoffs
        return $this->handoffs()->count();
    }

    /**
     * Get the count of tool calls for this trace.
     */
    public function getToolCallCountAttribute()
    {
        // If the value is already set in the database, return it
        if (isset($this->attributes['tool_call_count']) && $this->attributes['tool_call_count'] !== null) {
            return (int) $this->attributes['tool_call_count'];
        }
        
        // If this is a root trace, count all tool calls in this trace hierarchy
        if ($this->parent_id === null) {
            $tableName = $this->getTable();
            $connection = $this->getConnectionName();
            
            // Use a recursive query to find all descendants of this trace
            $descendants = $this->getAllDescendantIds();
            
            // Count tool calls among descendants
            return DB::connection($connection)->table($tableName)
                ->whereIn('id', $descendants)
                ->where('type', 'tool_call')
                ->count();
        }
        
        // Otherwise just count direct tool calls
        return $this->toolCalls()->count();
    }

    /**
     * Helper method to get all descendant IDs recursively.
     * 
     * @return array
     */
    private function getAllDescendantIds()
    {
        $tableName = $this->getTable();
        $connection = $this->getConnectionName();
        $traceId = $this->id;
        
        // Start with direct children
        $descendants = DB::connection($connection)->table($tableName)
            ->where('parent_id', $traceId)
            ->pluck('id')
            ->toArray();
            
        // Add indirect descendants recursively
        $newDescendants = $descendants;
        while (!empty($newDescendants)) {
            $nextLevel = DB::connection($connection)->table($tableName)
                ->whereIn('parent_id', $newDescendants)
                ->pluck('id')
                ->toArray();
                
            if (empty($nextLevel)) {
                break;
            }
            
            $descendants = array_merge($descendants, $nextLevel);
            $newDescendants = $nextLevel;
        }
        
        return $descendants;
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