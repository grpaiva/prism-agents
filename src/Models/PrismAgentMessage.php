<?php

namespace Grpaiva\PrismAgents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrismAgentMessage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'prism_agent_messages';

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
        'content',
        'tool_calls',
        'additional_content',
        'message_index',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tool_calls' => 'array',
        'additional_content' => 'array',
        'message_index' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the step that owns this message.
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(PrismAgentStep::class, 'step_id');
    }
} 