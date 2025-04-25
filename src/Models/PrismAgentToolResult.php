<?php

namespace Grpaiva\PrismAgents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrismAgentToolResult extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'prism_agent_tool_results';

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
        'tool_call_id',
        'tool_name',
        'args',
        'result',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'args' => 'array',
        'result' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the tool call that owns this result.
     */
    public function toolCall(): BelongsTo
    {
        return $this->belongsTo(PrismAgentToolCall::class, 'tool_call_id');
    }
} 