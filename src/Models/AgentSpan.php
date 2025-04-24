<?php

namespace Grpaiva\PrismAgents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class AgentSpan extends Model
{
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
        'error' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
        'duration_ms' => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'object',
        'created_at',
        'duration_ms',
        'started_at',
        'ended_at',
        'trace_id',
        'parent_id',
        'span_data',
        'error',
        'speech_group_output',
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
        
        // Set created_at to started_at by default
        if (!isset($attributes['created_at']) && isset($attributes['started_at'])) {
            $attributes['created_at'] = $attributes['started_at'];
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
            
            // Set created_at to match started_at if not explicitly set
            if (!$model->created_at) {
                $model->created_at = $model->started_at;
            }
            
            // Format the ID if not set
            if (!$model->id) {
                $model->id = 'span_' . substr(md5(uniqid()), 0, 24);
            }
            
            // Set object type if not set
            if (!$model->object) {
                $model->object = 'trace.span';
            }
        });
    }

    /**
     * Get the trace that owns the span.
     */
    public function trace()
    {
        return $this->belongsTo(AgentTrace::class, 'trace_id', 'id');
    }

    /**
     * Get the parent span.
     */
    public function parent()
    {
        return $this->belongsTo(AgentSpan::class, 'parent_id');
    }

    /**
     * Get the child spans.
     */
    public function children()
    {
        return $this->hasMany(AgentSpan::class, 'parent_id');
    }

    /**
     * Check if this span is an agent span
     */
    public function isAgentSpan()
    {
        return isset($this->span_data['type']) && $this->span_data['type'] === 'agent';
    }

    /**
     * Check if this span is a response span
     */
    public function isResponseSpan()
    {
        return isset($this->span_data['type']) && $this->span_data['type'] === 'response';
    }

    /**
     * Check if this span is a handoff span
     */
    public function isHandoffSpan()
    {
        return isset($this->span_data['type']) && $this->span_data['type'] === 'handoff';
    }

    /**
     * Check if this span is a function/tool span
     */
    public function isFunctionSpan()
    {
        return isset($this->span_data['type']) && $this->span_data['type'] === 'function';
    }

    /**
     * Get the agent name for this span
     */
    public function getAgentNameAttribute()
    {
        if ($this->isAgentSpan() && isset($this->span_data['name'])) {
            return $this->span_data['name'];
        }
        
        if ($this->isHandoffSpan()) {
            if (isset($this->span_data['from_agent'])) {
                return $this->span_data['from_agent'];
            }
        }
        
        return null;
    }

    /**
     * Get the span type
     */
    public function getSpanTypeAttribute()
    {
        return $this->span_data['type'] ?? null;
    }

    /**
     * Get any tool names used in this span
     */
    public function getToolsAttribute()
    {
        if ($this->isAgentSpan() && isset($this->span_data['tools'])) {
            return $this->span_data['tools'];
        }
        
        if ($this->isFunctionSpan() && isset($this->span_data['name'])) {
            return [$this->span_data['name']];
        }
        
        return [];
    }

    /**
     * Get handoff targets used in this span
     */
    public function getHandoffsAttribute()
    {
        if ($this->isAgentSpan() && isset($this->span_data['handoffs'])) {
            return $this->span_data['handoffs'];
        }
        
        if ($this->isHandoffSpan() && isset($this->span_data['to_agent'])) {
            return [$this->span_data['to_agent']];
        }
        
        return [];
    }

    /**
     * Get the response ID if this is a response span
     */
    public function getResponseIdAttribute()
    {
        if ($this->isResponseSpan() && isset($this->span_data['response_id'])) {
            return $this->span_data['response_id'];
        }
        
        return null;
    }

    /**
     * Get all descendants of this span
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }
} 