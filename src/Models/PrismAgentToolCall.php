<?php

namespace Grpaiva\PrismAgents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PrismAgentToolCall extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'prism_agent_tool_calls';

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
        'step_id',
        'call_id',
        'name',
        'args',
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
        'args' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration' => 'integer',
    ];

    /**
     * Get the step that owns this tool call.
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(PrismAgentStep::class, 'step_id');
    }

    /**
     * Get the result for this tool call.
     */
    public function result(): HasOne
    {
        return $this->hasOne(PrismAgentToolResult::class, 'tool_call_id');
    }

    /**
     * Calculate the duration of the tool call.
     */
    public function calculateDuration(): void
    {
        if ($this->started_at && $this->ended_at) {
            $this->duration = $this->ended_at->diffInMilliseconds($this->started_at);
            $this->save();
        }
    }

    /**
     * Mark the tool call as completed.
     */
    public function markCompleted(): void
    {
        $this->ended_at = now();
        $this->save();
        $this->calculateDuration();
    }
} 