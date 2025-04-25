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
        // Main executions table
        Schema::create('prism_agent_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()->index();
            $table->bigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->string('type')->default('execution');
            $table->string('status')->default('running');
            $table->text('error_message')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->json('meta')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable()->comment('Duration in milliseconds');
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['user_id', 'started_at']);
            $table->index(['status', 'duration']);
        });

        // Steps table
        Schema::create('prism_agent_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('execution_id')->constrained('prism_agent_executions')->onDelete('cascade');
            $table->integer('step_index');
            $table->text('text')->nullable();
            $table->string('finish_reason')->nullable();
            $table->json('usage')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable()->comment('Duration in milliseconds');
            $table->timestamps();

            $table->index(['execution_id', 'step_index']);
        });

        // Tool calls table
        Schema::create('prism_agent_tool_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('step_id')->constrained('prism_agent_steps')->onDelete('cascade');
            $table->string('call_id');
            $table->string('name');
            $table->json('args')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable()->comment('Duration in milliseconds');
            $table->timestamps();

            $table->index(['step_id', 'call_id']);
        });

        // Tool results table
        Schema::create('prism_agent_tool_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tool_call_id')->constrained('prism_agent_tool_calls')->onDelete('cascade');
            $table->string('tool_name');
            $table->json('args')->nullable();
            $table->text('result');
            $table->timestamps();

            $table->index(['tool_call_id', 'tool_name']);
        });

        // Messages table
        Schema::create('prism_agent_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('step_id')->constrained('prism_agent_steps')->onDelete('cascade');
            $table->text('content')->nullable();
            $table->json('tool_calls')->nullable();
            $table->json('additional_content')->nullable();
            $table->integer('message_index');
            $table->timestamps();

            $table->index(['step_id', 'message_index']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prism_agent_messages');
        Schema::dropIfExists('prism_agent_tool_results');
        Schema::dropIfExists('prism_agent_tool_calls');
        Schema::dropIfExists('prism_agent_steps');
        Schema::dropIfExists('prism_agent_executions');
    }
}; 