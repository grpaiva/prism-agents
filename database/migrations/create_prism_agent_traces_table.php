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
            $table->string('id')->primary(); // Using string format like trace_716bb2ff573d4e72ab2388245cbe26f9
            $table->string('object')->default('trace');
            $table->timestamp('created_at');
            $table->integer('duration_ms')->nullable();
            $table->string('workflow_name')->nullable();
            $table->string('group_id')->nullable()->index();
            $table->integer('handoff_count')->nullable();
            $table->integer('tool_count')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index('created_at');
        });

        Schema::create('prism_agent_spans', function (Blueprint $table) {
            $table->string('id')->primary(); // Using string format like span_e4b91ff7235b4123964db546
            $table->string('object')->default('trace.span');
            $table->timestamp('created_at');
            $table->integer('duration_ms')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('trace_id');
            $table->string('parent_id')->nullable();
            $table->json('span_data')->nullable();
            $table->json('error')->nullable();
            $table->text('speech_group_output')->nullable();
            $table->timestamps();
            
            // Foreign keys and indexes
            $table->foreign('trace_id')->references('id')->on('prism_agent_traces')->onDelete('cascade');
            $table->index(['trace_id', 'started_at']);
            $table->index('parent_id');
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
        Schema::dropIfExists('prism_agent_traces');
    }
}; 