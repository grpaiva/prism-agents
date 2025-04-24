<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;

return new class extends Migration
{
    /**
     * Get the migration connection name.
     */
    public function getConnection(): string|null
    {
        return Config::get('prism-agents.tracing.connection') ?: config('database.default');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prism_agent_spans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Foreign key to link to the overall execution
            $table->foreignUuid('execution_id')
                  ->constrained('prism_agent_executions') // Assumes the executions table is named 'prism_agent_executions'
                  ->onDelete('cascade');
            
            // Foreign key for parent span (self-referencing)
            $table->uuid('parent_span_id')->nullable()->index();
            $table->foreign('parent_span_id')
                  ->references('id')
                  ->on('prism_agent_spans')
                  ->onDelete('cascade');
                  
            $table->string('name')->comment('Context-dependent name (e.g., agent name, step name, tool name).');
            $table->string('type')->index()->comment('Type of span: agent, llm_step, tool_call, handoff.');
            $table->string('status')->index()->default('running')->comment('Span status: running, success, error.');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable()->comment('Duration in milliseconds.');
            
            // Consolidated data specific to the span type
            $table->json('span_data')->comment('Type-specific data (inputs, outputs, metadata, etc.).');
            
            // Consolidated error information
            $table->json('error')->nullable()->comment('Error details if the span failed.');
            
            $table->timestamps();
            
            // Additional indexes for querying
            $table->index(['execution_id', 'started_at']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prism_agent_spans');
    }
}; 