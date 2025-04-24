<?php

namespace Grpaiva\PrismAgents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentExecution extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'prism_agent_executions';

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
        'metadata' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_ms' => 'integer',
        'handoff_count' => 'integer',
        'tool_call_count' => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'workflow_name',
        'group_id',
        'status',
        'started_at',
        'ended_at',
        'duration_ms',
        'handoff_count',
        'tool_call_count',
        'metadata',
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
        });
    }

    /**
     * Get the spans associated with this execution.
     */
    public function spans(): HasMany
    {
        return $this->hasMany(AgentSpan::class, 'execution_id');
    }

    /**
     * Get the root span for this execution.
     */
    public function rootSpan(): HasMany
    {
        return $this->spans()->whereNull('parent_span_id');
    }
} 