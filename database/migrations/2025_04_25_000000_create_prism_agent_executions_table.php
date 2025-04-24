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
        Schema::create('prism_agent_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('workflow_name')->index()->comment('Name of the root agent or workflow.');
            $table->string('group_id')->nullable()->index()->comment('Optional user-defined grouping ID.');
            $table->string('status')->index()->default('running')->comment('Execution status: running, completed, failed.');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable()->comment('Total duration in milliseconds.');
            $table->unsignedInteger('handoff_count')->default(0)->comment('Total handoffs in this execution.');
            $table->unsignedInteger('tool_call_count')->default(0)->comment('Total tool calls in this execution.');
            $table->json('metadata')->nullable()->comment('User-defined metadata for the execution.');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prism_agent_executions');
    }
}; 