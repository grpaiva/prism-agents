<?php

namespace Grpaiva\PrismAgents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;

class AgentExecution extends Model
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
        'system_message' => 'array',
        'configuration' => 'array',
        'functions' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_ms' => 'integer',
        'total_tokens' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
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
        'workflow',
        'flow',
        'agent_name',
        'provider',
        'model',
        'input',
        'output',
        'status',
        'error',
        'total_tokens',
        'prompt_tokens',
        'completion_tokens',
        'handoff_count',
        'tool_count',
        'system_message',
        'configuration',
        'functions',
        'started_at',
        'ended_at',
        'duration_ms',
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
        'handoff_count' => 0,
        'tool_count' => 0,
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
            $this->table = Config::get('prism-agents.tracing.executions_table', 'prism_agent_executions');
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
     * Get all spans associated with this execution.
     */
    public function spans(): HasMany
    {
        return $this->hasMany(AgentSpan::class, 'execution_id');
    }

    /**
     * Get only the root spans (no parent) for this execution.
     */
    public function rootSpans(): HasMany
    {
        return $this->spans()->whereNull('parent_span_id');
    }

    /**
     * Scope a query to only include executions with handoffs.
     */
    public function scopeWithHandoffs($query)
    {
        return $query->where('handoff_count', '>', 0);
    }

    /**
     * Scope a query to only include executions with tool calls.
     */
    public function scopeWithTools($query)
    {
        return $query->where('tool_count', '>', 0);
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
     * Mark the execution as complete.
     *
     * @param string $status Status ('success' or 'error')
     * @param string|null $output Final output text
     * @param array|null $error Error details if status is 'error'
     * @param array|null $usage Token usage information
     * @return $this
     */
    public function finish(string $status = 'success', ?string $output = null, ?array $error = null, ?array $usage = null)
    {
        $this->status = $status;
        
        if ($output !== null) {
            $this->output = $output;
        }
        
        if ($error !== null) {
            $this->error = json_encode($error);
        }
        
        if ($usage !== null) {
            $this->total_tokens = $usage['total_tokens'] ?? null;
            $this->prompt_tokens = $usage['prompt_tokens'] ?? null;
            $this->completion_tokens = $usage['completion_tokens'] ?? null;
        }
        
        $this->ended_at = now();
        $this->duration_ms = $this->started_at->diffInMilliseconds($this->ended_at);
        
        // Update counts based on spans
        $this->updateCounts();
        
        $this->save();
        return $this;
    }

    /**
     * Update the handoff and tool counts based on associated spans.
     */
    protected function updateCounts()
    {
        $this->handoff_count = $this->spans()->where('type', 'handoff')->count();
        $this->tool_count = $this->spans()->where('type', 'tool_call')->count();
    }

    /**
     * Build the flow representation based on handoffs.
     */
    public function buildFlow()
    {
        $agents = [];
        $seenAgents = [];
        
        // Start with the primary agent
        if ($this->agent_name) {
            $agents[] = $this->agent_name;
            $seenAgents[$this->agent_name] = true;
        }
        
        // Get all handoff spans
        $handoffs = $this->spans()
            ->where('type', 'handoff')
            ->orderBy('started_at')
            ->get();
            
        foreach ($handoffs as $handoff) {
            $targetAgent = $handoff->handoff_target;
            
            if ($targetAgent && !isset($seenAgents[$targetAgent])) {
                $agents[] = $targetAgent;
                $seenAgents[$targetAgent] = true;
            }
        }
        
        // Create flow string
        $this->flow = count($agents) > 1 ? implode(' → ', $agents) : null;
        $this->save();
        
        return $this->flow;
    }
} 