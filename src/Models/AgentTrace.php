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
        'duration_ms' => 'integer',
        'handoff_count' => 'integer',
        'tool_count' => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'object',
        'duration_ms',
        'workflow_name',
        'group_id',
        'handoff_count',
        'tool_count',
        'metadata',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'formatted_duration',
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

        parent::__construct($attributes);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Format the ID if not set
            if (!$model->id) {
                $model->id = 'trace_' . substr(md5(uniqid()), 0, 32);
            }
            
            // Set object type if not set
            if (!$model->object) {
                $model->object = 'trace';
            }
        });
    }

    /**
     * Get the spans for this trace.
     */
    public function spans()
    {
        return $this->hasMany(AgentSpan::class, 'trace_id');
    }

    /**
     * Get the agent spans for this trace.
     */
    public function agentSpans()
    {
        return $this->spans()->whereHas('span_data', function ($query) {
            $query->where('type', 'agent');
        });
    }

    /**
     * Get the response spans for this trace.
     */
    public function responseSpans()
    {
        return $this->spans()->whereHas('span_data', function ($query) {
            $query->where('type', 'response');
        });
    }

    /**
     * Get the handoff spans for this trace.
     */
    public function handoffSpans()
    {
        return $this->spans()->whereHas('span_data', function ($query) {
            $query->where('type', 'handoff');
        });
    }

    /**
     * Get the function/tool spans for this trace.
     */
    public function functionSpans()
    {
        return $this->spans()->whereHas('span_data', function ($query) {
            $query->where('type', 'function');
        });
    }

    /**
     * Get the root spans for this trace (spans with no parent).
     */
    public function rootSpans()
    {
        return $this->spans()->whereNull('parent_id');
    }

    /**
     * Calculate and update the counts for this trace.
     */
    public function calculateCounts()
    {
        $this->handoff_count = $this->handoffSpans()->count();
        $this->tool_count = $this->functionSpans()->count();
        return $this;
    }

    /**
     * Calculate and update the duration for this trace.
     */
    public function calculateDuration()
    {
        $spans = $this->spans;
        
        if ($spans->isEmpty()) {
            return $this;
        }
        
        $startedAt = $spans->min('started_at');
        $endedAt = $spans->max('ended_at');
        
        if ($startedAt && $endedAt) {
            $this->duration_ms = $endedAt->diffInMicroseconds($startedAt) / 1000;
        }
        
        return $this;
    }

    /**
     * Get the first 5 agent names in this trace.
     */
    public function getFirst5AgentsAttribute()
    {
        return $this->spans()
            ->whereRaw("JSON_EXTRACT(span_data, '$.type') = 'agent'")
            ->orderBy('started_at')
            ->limit(5)
            ->get()
            ->pluck('span_data.name')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get the trace duration in a formatted string.
     */
    public function getFormattedDurationAttribute()
    {
        if ($this->duration_ms === null) {
            return 'N/A';
        }
        
        // Format based on duration size
        if ($this->duration_ms < 1000) {
            return number_format($this->duration_ms, 2) . ' ms';
        } else {
            return number_format($this->duration_ms / 1000, 2) . ' s';
        }
    }

    /**
     * Get all spans in a hierarchical structure.
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getSpanHierarchy()
    {
        // Get all spans for this trace
        $spans = $this->spans()->orderBy('started_at')->get();
        
        // Build a lookup table
        $spansById = [];
        foreach ($spans as $span) {
            $spansById[$span->id] = [
                'model' => $span,
                'children' => [],
                'level' => 0,
            ];
        }
        
        // Build the hierarchy
        $rootSpans = [];
        foreach ($spans as $span) {
            if ($span->parent_id === null) {
                // Root span
                $rootSpans[] = &$spansById[$span->id];
            } else if (isset($spansById[$span->parent_id])) {
                // Add as child and set level
                $spansById[$span->id]['level'] = $spansById[$span->parent_id]['level'] + 1;
                $spansById[$span->parent_id]['children'][] = &$spansById[$span->id];
            }
        }
        
        return $rootSpans;
    }

    /**
     * Get a flat list of spans with level information.
     * 
     * @return array
     */
    public function getFlattenedSpanHierarchy()
    {
        $hierarchy = $this->getSpanHierarchy();
        $flatList = [];
        $this->flattenHierarchy($hierarchy, $flatList, true);
        return $flatList;
    }
    
    /**
     * Helper method to flatten a hierarchical span structure.
     */
    private function flattenHierarchy($items, &$result, $parentExpanded = true)
    {
        foreach ($items as $item) {
            // Add the current item
            $item['visible'] = $parentExpanded;
            $result[] = $item;
            
            // Add its children
            if (!empty($item['children'])) {
                $this->flattenHierarchy($item['children'], $result, $parentExpanded);
            }
        }
    }

    /**
     * Find a trace by its ID.
     *
     * @param string $traceId
     * @return self|null
     */
    public static function findTrace($traceId)
    {
        // If the ID doesn't have the prefix, add it
        if (strpos($traceId, 'trace_') !== 0) {
            $traceId = 'trace_' . $traceId;
        }
        
        return self::find($traceId);
    }
} 