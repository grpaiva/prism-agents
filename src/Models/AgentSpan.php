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
    protected $table = 'prism_agent_spans';

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = null;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'span_data' => 'array',
        'error' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_ms' => 'integer',
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
        'started_at',
        'ended_at',
        'duration_ms',
        'span_data',
        'error',
    ];

    /**
     * Default attribute values.
     *
     * @var array
     */
    protected $attributes = [
        'started_at' => null,
        'status' => 'running',
        'span_data' => '[]', // Default to empty JSON object
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
        
        // Set default status if not provided
        if (!isset($attributes['status'])) {
            $attributes['status'] = 'running';
        }
        
        // Ensure span_data is at least an empty array if not provided
        if (!isset($attributes['span_data'])) {
            $attributes['span_data'] = [];
        } else if (is_string($attributes['span_data'])) {
            // Attempt to decode if it's a JSON string
            $decoded = json_decode($attributes['span_data'], true);
            $attributes['span_data'] = is_array($decoded) ? $decoded : [];
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
            // Ensure status is set
            if (!$model->status) {
                $model->status = 'running';
            }
            // Initial sanitization and encoding for creating
            // Make sure span_data is an array before sanitizing
            $spanData = is_array($model->span_data) 
                ? $model->span_data 
                : (json_decode($model->span_data, true) ?? []);
            $model->attributes['span_data'] = json_encode(self::sanitizeDataForJson($spanData));
            
            // Also sanitize error if it's set during creation (less likely but possible)
            if (!empty($model->error)) {
                 $errorData = is_array($model->error) 
                    ? $model->error 
                    : (json_decode($model->error, true) ?? []);
                $model->attributes['error'] = json_encode(self::sanitizeDataForJson($errorData));
            }
        });
        
        static::saving(function ($model) {
             // Always sanitize and encode dirty JSON attributes before saving
            if ($model->isDirty('span_data')) {
                $spanData = is_array($model->span_data) 
                    ? $model->span_data 
                    : (json_decode($model->span_data, true) ?? []);
                // Use rawAttributes to prevent triggering mutators again if span_data has one
                $model->attributes['span_data'] = json_encode(self::sanitizeDataForJson($spanData));
            }
            if ($model->isDirty('error')) {
                 $errorData = is_array($model->error) 
                    ? $model->error 
                    : (json_decode($model->error, true) ?? []);
                $model->attributes['error'] = json_encode(self::sanitizeDataForJson($errorData));
            }
        });
    }

    /**
     * Recursively sanitize data for JSON encoding, converting non-backed enums.
     */
    protected static function sanitizeDataForJson($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitizeDataForJson($value);
            }
        } elseif (is_object($data)) {
            if ($data instanceof \UnitEnum && !($data instanceof \BackedEnum)) {
                 // Convert non-backed enum to its name
                return $data->name; 
            } elseif ($data instanceof \BackedEnum) {
                 // Convert backed enum to its value
                return $data->value; 
            } elseif (method_exists($data, 'toArray')) {
                 // If object has toArray, recursively sanitize its array form
                return self::sanitizeDataForJson($data->toArray());
            } elseif ($data instanceof \stdClass) {
                // Convert stdClass to array and sanitize
                return self::sanitizeDataForJson((array) $data);
            } 
            // Keep other objects as they are (let json_encode handle them or fail)
        }
        // Return scalar values or sanitized arrays/values
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
     * Scope a query to only include spans for a specific execution ID.
     */
    public function scopeForExecution($query, $executionId)
    {
        return $query->where('execution_id', $executionId);
    }

    /**
     * Check if the span has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
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
                'level' => 0, // Initialize level
                'visible' => false, // Initialize visibility
            ];
        }

        // Build the hierarchy and determine levels
        $rootSpans = [];
        foreach ($allSpans as $span) {
            if ($span->parent_span_id === null) {
                $rootSpans[] = &$spansById[$span->id];
                $spansById[$span->id]['level'] = 0; // Root level is 0
            } else if (isset($spansById[$span->parent_span_id])) {
                $parent = &$spansById[$span->parent_span_id];
                $parent['children'][] = &$spansById[$span->id];
                // Calculate level based on parent
                $spansById[$span->id]['level'] = $parent['level'] + 1;
            }
        }
        
        // Flatten the hierarchy into a display list
        $flatList = [];
        self::flattenHierarchy($rootSpans, $flatList, true); // Assume root is expanded by default
        
        // Ensure visibility is set correctly after flattening
        // The flattenHierarchy function now handles initial visibility.

        return $flatList;
    }

    /**
     * Helper method to flatten a hierarchical span structure.
     */
    private static function flattenHierarchy(array $items, array &$result, bool $parentIsVisible = true): void
    {
        foreach ($items as &$item) { // Pass item by reference to modify it
            // Set visibility based on parent
            $item['visible'] = $parentIsVisible;
            
            // Add the current item (without children array in flat list)
            $flatItem = $item;
            unset($flatItem['children']); // Remove children reference for the flat list
            $result[] = $flatItem;

            // Recursively process children if they exist
            if (!empty($item['children'])) {
                // Children are only visible if the current item is also visible
                self::flattenHierarchy($item['children'], $result, $parentIsVisible);
            }
        }
    }
} 