<?php

namespace Grpaiva\PrismAgents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class AgentSpan extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = null;

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
        'span_data' => 'array',
        'request_data' => 'array',
        'response_data' => 'array',
        'functions' => 'array',
        'error' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_ms' => 'integer',
        'tokens' => 'integer',
        'level' => 'integer',
        'is_visible' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'execution_id',
        'parent_span_id',
        'name',
        'type',
        'status',
        'level',
        'is_visible',
        'started_at',
        'ended_at',
        'duration_ms',
        'endpoint',
        'method',
        'model',
        'handoff_target',
        'tool_name',
        'request_data',
        'response_data',
        'tokens',
        'functions',
        'span_data',
        'error',
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
     * Default attribute values.
     * 
     * @var array
     */
    protected $attributes = [
        'status' => 'running',
        'level' => 0,
        'is_visible' => true,
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
        
        // Set the table name from config
        if (!$this->table) {
            $this->table = Config::get('prism-agents.tracing.spans_table', 'prism_agent_spans');
        }

        // Set default timestamp for started_at if not provided
        if (!isset($attributes['started_at'])) {
            $attributes['started_at'] = now();
        }

        // JSON field initialization
        foreach (['span_data', 'request_data', 'response_data', 'functions', 'error'] as $field) {
            if (isset($attributes[$field]) && is_string($attributes[$field])) {
                $decoded = json_decode($attributes[$field], true);
                $attributes[$field] = is_array($decoded) ? $decoded : [];
            } elseif (!isset($attributes[$field])) {
                $attributes[$field] = [];
            }
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
            
            // Process data based on span type
            $model->processSpanTypeData();
        });
        
        static::saving(function ($model) {
            // Ensure all JSON data is properly encoded before saving
            self::sanitizeJsonFields($model);
        });
    }

    /**
     * Process data based on the span type to ensure the right fields are populated.
     */
    protected function processSpanTypeData()
    {
        switch ($this->type) {
            case 'api_call':
                $this->ensureEndpointFromName();
                break;
                
            case 'handoff':
                $this->ensureHandoffTargetFromData();
                break;
                
            case 'tool_call':
                $this->ensureToolNameFromData();
                break;
        }
    }
    
    /**
     * Ensure endpoint is set for API calls, defaulting to name if needed.
     */
    protected function ensureEndpointFromName()
    {
        if (empty($this->endpoint) && !empty($this->name)) {
            $this->endpoint = $this->name;
        }
    }
    
    /**
     * Ensure handoff_target is set for handoff spans.
     */
    protected function ensureHandoffTargetFromData()
    {
        if (empty($this->handoff_target)) {
            // Try to get from span_data or request_data
            if (!empty($this->span_data) && !empty($this->span_data['tool_name'])) {
                $this->handoff_target = $this->span_data['tool_name'];
            } elseif (!empty($this->request_data) && !empty($this->request_data['tool_name'])) {
                $this->handoff_target = $this->request_data['tool_name'];
            }
        }
    }
    
    /**
     * Ensure tool_name is set for tool_call spans.
     */
    protected function ensureToolNameFromData()
    {
        if (empty($this->tool_name)) {
            // Try to get from span_data or request_data
            if (!empty($this->span_data) && !empty($this->span_data['tool_name'])) {
                $this->tool_name = $this->span_data['tool_name'];
            } elseif (!empty($this->request_data) && !empty($this->request_data['tool_name'])) {
                $this->tool_name = $this->request_data['tool_name'];
            }
        }
    }
    
    /**
     * Sanitize JSON fields to ensure they're properly encoded.
     */
    protected static function sanitizeJsonFields($model)
    {
        foreach (['span_data', 'request_data', 'response_data', 'functions', 'error'] as $field) {
            if ($model->isDirty($field)) {
                $data = $model->attributes[$field] ?? [];
                
                // If it's already a JSON string, decode it first
                if (is_string($data) && !empty($data)) {
                    $decoded = json_decode($data, true);
                    $data = is_array($decoded) ? $decoded : $data;
                }
                
                // Now ensure it's a properly sanitized array
                $sanitized = self::sanitizeDataForJson($data);
                
                // Use attributes array to avoid triggering any mutators
                $model->attributes[$field] = json_encode($sanitized);
            }
        }
    }

    /**
     * Recursively sanitize data for JSON encoding.
     */
    protected static function sanitizeDataForJson($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitizeDataForJson($value);
            }
            return $data;
        } elseif (is_object($data)) {
            if ($data instanceof \UnitEnum) {
                return ($data instanceof \BackedEnum) ? $data->value : $data->name;
            } elseif (method_exists($data, 'toArray')) {
                return self::sanitizeDataForJson($data->toArray());
            } elseif ($data instanceof \stdClass) {
                return self::sanitizeDataForJson((array) $data);
            } else {
                // Try to convert to string or array if possible
                try {
                    return (string) $data;
                } catch (\Throwable $e) {
                    return get_object_vars($data);
                }
            }
        }
        
        // Return scalar values as is
        return $data;
    }

    /**
     * Get the execution this span belongs to.
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(AgentExecution::class, 'execution_id');
    }

    /**
     * Get the parent span.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AgentSpan::class, 'parent_span_id');
    }

    /**
     * Get the child spans.
     */
    public function children(): HasMany
    {
        return $this->hasMany(AgentSpan::class, 'parent_span_id');
    }

    /**
     * Get a formatted representation of the duration.
     */
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration_ms) {
            return 'N/A';
        }

        if ($this->duration_ms < 1000) {
            return $this->duration_ms . 'ms';
        } elseif ($this->duration_ms < 60000) {
            return round($this->duration_ms / 1000, 2) . 's';
        } else {
            return round($this->duration_ms / 60000, 2) . 'm';
        }
    }

    /**
     * Scope a query to only include spans for a specific execution.
     */
    public function scopeForExecution($query, $executionId)
    {
        return $query->where('execution_id', $executionId);
    }

    /**
     * Scope a query to only include spans of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if the span has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Complete the span with results.
     *
     * @param string $status Status ('success' or 'error')
     * @param array|null $response Response data or result
     * @param array|null $error Error details if status is 'error'
     * @return $this
     */
    public function complete(string $status = 'success', ?array $response = null, ?array $error = null)
    {
        $this->status = $status;
        
        if ($response !== null) {
            $this->response_data = $response;
        }
        
        if ($error !== null) {
            $this->error = $error;
        }
        
        $this->ended_at = now();
        $this->duration_ms = $this->started_at->diffInMilliseconds($this->ended_at);
        
        $this->save();
        return $this;
    }

    /**
     * Create a basic icon representation based on the span type.
     */
    public function getIconAttribute(): string
    {
        switch ($this->type) {
            case 'agent':
                return '🤖';
            case 'api_call':
                return '🌐';
            case 'handoff':
                return '🔄';
            case 'tool_call':
                return '🔧';
            case 'llm_step':
                return '💬';
            default:
                return '📋';
        }
    }

    /**
     * Build a hierarchical structure for all spans within an execution.
     *
     * @param string $executionId The ID of the execution
     * @return array
     */
    public static function buildHierarchy($executionId): array
    {
        $allSpans = self::where('execution_id', $executionId)
            ->orderBy('started_at')
            ->get();

        if ($allSpans->isEmpty()) {
            return [];
        }

        // Build a lookup table and structure for hierarchy
        $spansById = [];
        foreach ($allSpans as $span) {
            $spansById[$span->id] = [
                'model' => $span,
                'children' => [],
                'level' => 0,
                'visible' => true,
            ];
        }
        
        // Build the hierarchy
        $result = [];
        foreach ($allSpans as $span) {
            if ($span->parent_span_id === null) {
                // Root span
                $result[] = &$spansById[$span->id];
            } else if (isset($spansById[$span->parent_span_id])) {
                // Add as child and set level
                $spansById[$span->id]['level'] = $spansById[$span->parent_span_id]['level'] + 1;
                $spansById[$span->parent_span_id]['children'][] = &$spansById[$span->id];
            }
        }
        
        // Flatten the hierarchy into a display list with levels
        $flatList = [];
        self::flattenHierarchy($result, $flatList, true);
        
        return $flatList;
    }
    
    /**
     * Helper method to flatten a hierarchical span structure.
     *
     * @param array $items
     * @param array &$result
     * @param bool $parentVisible
     * @return void
     */
    private static function flattenHierarchy(array $items, array &$result, bool $parentVisible = true): void
    {
        foreach ($items as $item) {
            // Add the current item
            $item['visible'] = $parentVisible;
            $result[] = $item;
            
            // Add its children
            if (!empty($item['children'])) {
                self::flattenHierarchy($item['children'], $result, $parentVisible && $item['model']->is_visible);
            }
        }
    }
} 