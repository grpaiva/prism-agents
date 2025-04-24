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
        Schema::create('prism_agent_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('workflow')->index()->comment('The name of the workflow/function being executed');
            $table->text('flow')->nullable()->comment('Visual representation of agent flow with handoffs');
            $table->string('agent_name')->nullable()->index();
            $table->string('provider')->nullable()->index();
            $table->string('model')->nullable();
            $table->text('input')->nullable();
            $table->text('output')->nullable();
            $table->string('status')->default('running')->index(); // running, success, error
            $table->text('error')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('handoff_count')->nullable()->default(0);
            $table->integer('tool_count')->nullable()->default(0);
            $table->json('system_message')->nullable();
            $table->json('configuration')->nullable()->comment('Agent configuration settings');
            $table->json('functions')->nullable()->comment('Available functions/tools');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_ms')->nullable()->comment('Execution time in milliseconds');
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['status', 'duration_ms']);
            $table->index(['started_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prism_agent_executions');
    }
}; 