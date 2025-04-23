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
        Schema::create('prism_agent_traces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trace_id')->index();
            $table->uuid('parent_id')->nullable()->index();
            $table->string('name');
            $table->string('type'); // agent_execution, step, tool_call, etc.
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable()->comment('Duration in milliseconds');
            $table->json('metadata')->nullable();
            
            // Additional fields for better analysis
            $table->string('agent_name')->nullable()->index();
            $table->string('provider')->nullable()->index();
            $table->string('model')->nullable();
            $table->text('input_text')->nullable();
            $table->text('output_text')->nullable();
            $table->string('status')->nullable()->index(); // success, error
            $table->text('error_message')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->integer('step_count')->nullable();
            $table->integer('tool_call_count')->nullable();
            
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['trace_id', 'started_at']);
            $table->index(['status', 'duration']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prism_agent_traces');
    }
}; 