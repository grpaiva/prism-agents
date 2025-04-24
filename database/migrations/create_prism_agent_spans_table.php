<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prism_agent_spans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('execution_id')->index();
            $table->uuid('parent_span_id')->nullable()->index();
            $table->string('name')->comment('Name of the span (agent name, API endpoint, etc.)');
            $table->string('type')->comment('agent, api_call, handoff, tool_call, etc.');
            $table->string('status')->default('running'); // running, success, error
            $table->integer('level')->default(0)->comment('Nesting level for UI display');
            $table->boolean('is_visible')->default(true)->comment('UI display state');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_ms')->nullable()->comment('Duration in milliseconds');
            
            // Span-specific data - we store different fields based on span type
            $table->string('endpoint')->nullable()->comment('For API call spans');
            $table->string('method')->nullable()->comment('For API call spans (GET/POST/etc)');
            $table->string('model')->nullable()->comment('For agent spans');
            $table->string('handoff_target')->nullable()->comment('Target agent for handoff spans');
            $table->string('tool_name')->nullable()->comment('Tool name for tool_call spans');
            $table->json('request_data')->nullable()->comment('API request data or tool call arguments');
            $table->json('response_data')->nullable()->comment('API response data or tool results');
            $table->integer('tokens')->nullable()->comment('Token count for agent/LLM operations');
            $table->json('functions')->nullable()->comment('Available functions in this span');
            $table->json('span_data')->nullable()->comment('Additional span metadata');
            $table->json('error')->nullable()->comment('Error details if span failed');
            $table->timestamps();
            
            // Foreign key to execution
            $table->foreign('execution_id')->references('id')->on('prism_agent_executions')->onDelete('cascade');
            
            // Self-referencing foreign key for parent span
            $table->foreign('parent_span_id')->references('id')->on('prism_agent_spans')->onDelete('set null');
            
            // Indexes for efficient querying
            $table->index(['execution_id', 'started_at']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prism_agent_spans');
    }
}; 