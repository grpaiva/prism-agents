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