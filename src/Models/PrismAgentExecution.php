<?php

namespace Grpaiva\PrismAgents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Casts\Attribute;

class PrismAgentExecution extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'prism_agent_executions';

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
        'parent_id',
        'user_id',
        'name',
        'type',
        'status',
        'error_message',
        'provider',
        'model',
        'meta',
        'total_tokens',
        'prompt_tokens',
        'completion_tokens',
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
        'meta' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'total_tokens' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'duration' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['formatted_duration', 'handoff_count', 'tool_count'];

    /**
     * Get the parent execution if this is a nested execution.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PrismAgentExecution::class, 'parent_id');
    }

    /**
     * Get the child executions if this is a parent execution.
     */
    public function children(): HasMany
    {
        return $this->hasMany(PrismAgentExecution::class, 'parent_id');
    }

    /**
     * Get the steps for this execution.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(PrismAgentStep::class, 'execution_id');
    }

    /**
     * Get the user associated with this execution.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    /**
     * Calculate and set the duration of the execution.
     */
    public function calculateDuration(): void
    {
        if ($this->started_at && $this->ended_at) {
            $this->duration = $this->ended_at->diffInMilliseconds($this->started_at);
            $this->save();
        }
    }

    /**
     * Mark the execution as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);
        $this->calculateDuration();
    }

    /**
     * Mark the execution as failed.
     */
    public function markFailed(string $errorMessage = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'ended_at' => now(),
        ]);
        $this->calculateDuration();
    }
    
    /**
     * Get formatted duration in a human-readable format
     */
    protected function formattedDuration(): Attribute
    {
        return Attribute::make(
            get: function () {
                $duration = $this->duration ?? 0;
                
                if ($duration < 1000) {
                    return number_format($duration, 0) . 'ms';
                } elseif ($duration < 60000) {
                    return number_format($duration / 1000, 2) . 's';
                } else {
                    return number_format($duration / 60000, 2) . 'm';
                }
            }
        );
    }
    
    /**
     * Get the count of handoffs for this execution
     */
    protected function handoffCount(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->steps()
                    ->withCount('toolCalls')
                    ->get()
                    ->sum('tool_calls_count');
            }
        );
    }
    
    /**
     * Get the count of tools used in this execution
     */
    protected function toolCount(): Attribute
    {
        return Attribute::make(
            get: function () {
                $count = 0;
                $steps = $this->steps()->with('toolCalls.result')->get();
                
                foreach ($steps as $step) {
                    foreach ($step->toolCalls as $toolCall) {
                        if ($toolCall->result) {
                            $count++;
                        }
                    }
                }
                
                return $count;
            }
        );
    }
    
    /**
     * Get a formatted list of agent flow for display
     */
    public function getFlowText(): string
    {
        $childrenNames = $this->children()->pluck('name')->toArray();
        if (empty($childrenNames)) {
            return 'N/A';
        }
        
        return implode(' → ', $childrenNames);
    }
} 