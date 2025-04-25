<?php

namespace Grpaiva\PrismAgents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrismAgentStep extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'prism_agent_steps';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'id',
        'execution_id',
        'step_index',
        'text',
        'finish_reason',
        'usage',
        'meta',
        'started_at',
        'ended_at',
        'duration',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'usage' => 'array',
        'meta' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration' => 'integer',
        'step_index' => 'integer',
    ];

    /**
     * Get the execution that owns this step.
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(PrismAgentExecution::class, 'execution_id');
    }

    /**
     * Get the tool calls for this step.
     */
    public function toolCalls(): HasMany
    {
        return $this->hasMany(PrismAgentToolCall::class, 'step_id');
    }

    /**
     * Get the messages for this step.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(PrismAgentMessage::class, 'step_id');
    }

    /**
     * Calculate the duration of the step.
     */
    public function calculateDuration(): void
    {
        if ($this->started_at && $this->ended_at) {
            $this->duration = $this->ended_at->diffInMilliseconds($this->started_at);
            $this->save();
        }
    }

    /**
     * Mark the step as completed.
     */
    public function markCompleted(): void
    {
        $this->ended_at = now();
        $this->save();
        $this->calculateDuration();
    }
} 