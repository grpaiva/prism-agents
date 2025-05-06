<?php

namespace Grpaiva\PrismAgents\Tests\Unit\Models;

use Grpaiva\PrismAgents\Models\AgentTrace;
use Grpaiva\PrismAgents\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class AgentTraceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_casts_attributes_correctly()
    {
        $trace = new AgentTrace([
            'id' => 'test-id',
            'metadata' => ['status' => 'success'],
            'started_at' => '2023-01-01 00:00:00',
            'ended_at' => '2023-01-01 00:00:10',
            'duration' => -10.53,
        ]);

        $this->assertIsArray($trace->metadata);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $trace->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $trace->ended_at);
        $this->assertIsFloat($trace->duration);
    }

    public function test_it_uses_correct_connection()
    {
        // Set a custom connection in config
        Config::set('prism-agents.tracing.connection', 'testdb');

        $trace = new AgentTrace;
        $trace->id = 'test-id';

        // Just test that the model is using the correct connection name
        // without actually trying to save it to the database
        $this->assertEquals('testdb', $trace->getConnectionName());

        // Clean up
        Config::set('prism-agents.tracing.connection', null);
    }

    public function test_parent_child_relationship()
    {
        // Create parent trace
        $parent = new AgentTrace([
            'id' => 'parent-trace',
            'trace_id' => 'trace-1',
            'name' => 'Parent Trace',
            'type' => 'agent_execution',
        ]);
        $parent->saveQuietly();

        // Create child traces
        $child1 = new AgentTrace([
            'id' => 'child-trace-1',
            'trace_id' => 'trace-1',
            'parent_id' => 'parent-trace',
            'name' => 'Child Trace 1',
            'type' => 'llm_step',
        ]);
        $child1->saveQuietly();

        $child2 = new AgentTrace([
            'id' => 'child-trace-2',
            'trace_id' => 'trace-1',
            'parent_id' => 'parent-trace',
            'name' => 'Child Trace 2',
            'type' => 'tool_call',
        ]);
        $child2->saveQuietly();

        // Create grandchild trace
        $grandchild = new AgentTrace([
            'id' => 'grandchild-trace',
            'trace_id' => 'trace-1',
            'parent_id' => 'child-trace-1',
            'name' => 'Grandchild Trace',
            'type' => 'handoff',
        ]);
        $grandchild->saveQuietly();

        // Refresh models
        $parent = $parent->fresh();
        $child1 = $child1->fresh();

        // Test parent-child relationship
        $this->assertCount(2, $parent->children);
        $this->assertCount(1, $child1->children);
        $this->assertEquals('parent-trace', $child1->parent->id);
        $this->assertEquals('child-trace-1', $grandchild->parent->id);
        $this->assertTrue($parent->hasChildren());
    }

    public function test_handoff_relationship()
    {
        // Create a step trace
        $step = new AgentTrace([
            'id' => 'step-trace',
            'trace_id' => 'trace-1',
            'name' => 'step_0',
            'type' => 'llm_step',
            'metadata' => [
                'step_index' => 0,
                'tools' => ['tool1', 'tool2'],
            ],
        ]);
        $step->saveQuietly();

        // Create handoff traces
        $handoff1 = new AgentTrace([
            'id' => 'handoff-1',
            'trace_id' => 'trace-1',
            'parent_id' => 'step-trace',
            'name' => 'handoff_1',
            'type' => 'handoff',
            'metadata' => [
                'tool_name' => 'tool1',
                'result' => 'Result from tool1',
            ],
        ]);
        $handoff1->saveQuietly();

        $handoff2 = new AgentTrace([
            'id' => 'handoff-2',
            'trace_id' => 'trace-1',
            'parent_id' => 'step-trace',
            'name' => 'handoff_2',
            'type' => 'handoff',
            'metadata' => [
                'tool_name' => 'tool2',
                'result' => 'Result from tool2',
            ],
        ]);
        $handoff2->saveQuietly();

        // Create non-handoff child
        $otherChild = new AgentTrace([
            'id' => 'other-child',
            'trace_id' => 'trace-1',
            'parent_id' => 'step-trace',
            'name' => 'other_child',
            'type' => 'tool_call',
        ]);
        $otherChild->saveQuietly();

        // Refresh model
        $step = $step->fresh();

        // Test handoffs relationship
        $this->assertCount(2, $step->handoffs);
        $this->assertTrue($step->hasHandoffs());
        $this->assertEquals(2, $step->handoff_count);

        // Test tool calls relationship
        $this->assertCount(1, $step->toolCalls);
        $this->assertEquals(1, $step->tool_call_count);

        // Verify the handoffs are the right ones
        $handoffIds = $step->handoffs->pluck('id')->toArray();
        $this->assertContains('handoff-1', $handoffIds);
        $this->assertContains('handoff-2', $handoffIds);
        $this->assertNotContains('other-child', $handoffIds);
    }

    public function test_root_trace_handoff_and_tool_count()
    {
        // Create a root trace
        $root = new AgentTrace([
            'id' => 'root-trace',
            'trace_id' => 'trace-counts',
            'name' => 'Root Trace',
            'type' => 'agent_execution',
        ]);
        $root->saveQuietly();

        // Create child traces - step
        $step = new AgentTrace([
            'id' => 'step-trace',
            'trace_id' => 'trace-counts',
            'parent_id' => 'root-trace',
            'name' => 'step_0',
            'type' => 'llm_step',
        ]);
        $step->saveQuietly();

        // Create handoffs under the step
        $handoff1 = new AgentTrace([
            'id' => 'handoff-1',
            'trace_id' => 'trace-counts',
            'parent_id' => 'step-trace',
            'name' => 'handoff_1',
            'type' => 'handoff',
        ]);
        $handoff1->saveQuietly();

        $handoff2 = new AgentTrace([
            'id' => 'handoff-2',
            'trace_id' => 'trace-counts',
            'parent_id' => 'step-trace',
            'name' => 'handoff_2',
            'type' => 'handoff',
        ]);
        $handoff2->saveQuietly();

        // Create tool call under the step
        $toolCall = new AgentTrace([
            'id' => 'tool-call-1',
            'trace_id' => 'trace-counts',
            'parent_id' => 'step-trace',
            'name' => 'tool_call_1',
            'type' => 'tool_call',
        ]);
        $toolCall->saveQuietly();

        // Create an unrelated root trace with same trace_id
        $unrelatedRoot = new AgentTrace([
            'id' => 'unrelated-root',
            'trace_id' => 'trace-counts', // Same trace_id
            'name' => 'Unrelated Root',
            'type' => 'agent_execution',
        ]);
        $unrelatedRoot->saveQuietly();

        // Create an unrelated handoff and tool call
        $unrelatedHandoff = new AgentTrace([
            'id' => 'unrelated-handoff',
            'trace_id' => 'trace-counts',
            'parent_id' => 'unrelated-root',
            'name' => 'unrelated_handoff',
            'type' => 'handoff',
        ]);
        $unrelatedHandoff->saveQuietly();

        $unrelatedToolCall = new AgentTrace([
            'id' => 'unrelated-tool-call',
            'trace_id' => 'trace-counts',
            'parent_id' => 'unrelated-root',
            'name' => 'unrelated_tool_call',
            'type' => 'tool_call',
        ]);
        $unrelatedToolCall->saveQuietly();

        // Refresh the root trace
        $root = $root->fresh();

        // Test that the root trace counts include only its descendants
        $this->assertEquals(2, $root->handoff_count);
        $this->assertEquals(1, $root->tool_call_count);

        // Add another tool call directly under the root
        $toolCall2 = new AgentTrace([
            'id' => 'tool-call-2',
            'trace_id' => 'trace-counts',
            'parent_id' => 'root-trace',
            'name' => 'tool_call_2',
            'type' => 'tool_call',
        ]);
        $toolCall2->saveQuietly();

        // Refresh and test again
        $root = $root->fresh();
        $this->assertEquals(2, $root->handoff_count);
        $this->assertEquals(2, $root->tool_call_count);

        // Verify unrelated root only counts its own descendants
        $unrelatedRoot = $unrelatedRoot->fresh();
        $this->assertEquals(1, $unrelatedRoot->handoff_count);
        $this->assertEquals(1, $unrelatedRoot->tool_call_count);
    }

    public function test_get_all_descendant_ids()
    {
        // Create test hierarchy
        $root = new AgentTrace([
            'id' => 'root-trace',
            'trace_id' => 'trace-descendants',
            'name' => 'Root Trace',
            'type' => 'agent_execution',
        ]);
        $root->saveQuietly();

        $child1 = new AgentTrace([
            'id' => 'child-1',
            'trace_id' => 'trace-descendants',
            'parent_id' => 'root-trace',
            'name' => 'Child 1',
            'type' => 'llm_step',
        ]);
        $child1->saveQuietly();

        $child2 = new AgentTrace([
            'id' => 'child-2',
            'trace_id' => 'trace-descendants',
            'parent_id' => 'root-trace',
            'name' => 'Child 2',
            'type' => 'llm_step',
        ]);
        $child2->saveQuietly();

        $grandchild1 = new AgentTrace([
            'id' => 'grandchild-1',
            'trace_id' => 'trace-descendants',
            'parent_id' => 'child-1',
            'name' => 'Grandchild 1',
            'type' => 'handoff',
        ]);
        $grandchild1->saveQuietly();

        $greatgrandchild1 = new AgentTrace([
            'id' => 'greatgrandchild-1',
            'trace_id' => 'trace-descendants',
            'parent_id' => 'grandchild-1',
            'name' => 'Great Grandchild 1',
            'type' => 'tool_call',
        ]);
        $greatgrandchild1->saveQuietly();

        // Access the private method using reflection
        $reflection = new \ReflectionClass($root);
        $method = $reflection->getMethod('getAllDescendantIds');
        $method->setAccessible(true);

        $descendants = $method->invoke($root);

        // Verify all descendants are found
        $this->assertCount(4, $descendants);
        $this->assertContains('child-1', $descendants);
        $this->assertContains('child-2', $descendants);
        $this->assertContains('grandchild-1', $descendants);
        $this->assertContains('greatgrandchild-1', $descendants);
    }

    public function test_cached_tool_call_count()
    {
        // Create a trace with predefined tool_call_count
        $trace = new AgentTrace([
            'id' => 'trace-with-cached-count',
            'trace_id' => 'trace-counts',
            'name' => 'Cached Count Trace',
            'type' => 'agent_execution',
            'tool_call_count' => 5,
        ]);
        $trace->saveQuietly();

        // Test that the accessor returns the cached value
        $this->assertEquals(5, $trace->tool_call_count);
    }

    public function test_display_name_attribute()
    {
        // Test normal name
        $trace = new AgentTrace([
            'id' => 'test-id',
            'name' => 'Test Trace',
            'type' => 'agent_execution',
        ]);
        $this->assertEquals('Test Trace', $trace->display_name);

        // Test handoff with tool_name
        $handoff = new AgentTrace([
            'id' => 'handoff-id',
            'name' => 'handoff_1',
            'type' => 'handoff',
            'metadata' => [
                'tool_name' => 'spanish_agent',
            ],
        ]);
        $this->assertEquals('spanish_agent', $handoff->display_name);
    }

    public function test_step_index_attribute()
    {
        // Test with step index in metadata
        $step = new AgentTrace([
            'id' => 'step-id',
            'name' => 'step_0',
            'type' => 'llm_step',
            'metadata' => [
                'step_index' => 2,
            ],
        ]);
        $this->assertEquals(2, $step->step_index);

        // Test without step index
        $trace = new AgentTrace([
            'id' => 'test-id',
            'name' => 'Test Trace',
            'type' => 'llm_step',
        ]);
        $this->assertNull($trace->step_index);
    }

    public function test_handoff_target_attribute()
    {
        // Test handoff with tool_name
        $handoff = new AgentTrace([
            'id' => 'handoff-id',
            'type' => 'handoff',
            'metadata' => [
                'tool_name' => 'french_agent',
            ],
        ]);
        $this->assertEquals('french_agent', $handoff->handoff_target);

        // Test non-handoff
        $trace = new AgentTrace([
            'id' => 'test-id',
            'type' => 'agent_execution',
        ]);
        $this->assertNull($trace->handoff_target);
    }

    public function test_scope_root()
    {
        // Create root trace
        $rootTrace = new AgentTrace([
            'id' => 'root-trace',
            'trace_id' => 'trace-1',
            'name' => 'Root Trace',
            'type' => 'agent_execution',
        ]);
        $rootTrace->saveQuietly();

        // Create non-root trace
        $childTrace = new AgentTrace([
            'id' => 'child-trace',
            'trace_id' => 'trace-1',
            'parent_id' => 'root-trace',
            'name' => 'Child Trace',
            'type' => 'llm_step',
        ]);
        $childTrace->saveQuietly();

        // Test scope
        $rootTraces = AgentTrace::root()->get();
        $this->assertCount(1, $rootTraces);
        $this->assertEquals('root-trace', $rootTraces->first()->id);
    }

    public function test_scope_for_trace()
    {
        // Create traces for different trace IDs
        $trace1 = new AgentTrace([
            'id' => 'trace-1-root',
            'trace_id' => 'trace-1',
            'name' => 'Trace 1 Root',
            'type' => 'agent_execution',
        ]);
        $trace1->saveQuietly();

        $trace2 = new AgentTrace([
            'id' => 'trace-2-root',
            'trace_id' => 'trace-2',
            'name' => 'Trace 2 Root',
            'type' => 'agent_execution',
        ]);
        $trace2->saveQuietly();

        // Test scope
        $trace1Records = AgentTrace::forTrace('trace-1')->get();
        $this->assertCount(1, $trace1Records);
        $this->assertEquals('trace-1-root', $trace1Records->first()->id);
    }

    public function test_build_hierarchy()
    {
        // Create a hierarchy of traces
        $root = new AgentTrace([
            'id' => 'root-id',
            'trace_id' => 'trace-hier',
            'name' => 'Root',
            'type' => 'agent_execution',
        ]);
        $root->saveQuietly();

        $child1 = new AgentTrace([
            'id' => 'child1-id',
            'trace_id' => 'trace-hier',
            'parent_id' => 'root-id',
            'name' => 'Step 0',
            'type' => 'llm_step',
        ]);
        $child1->saveQuietly();

        $grandchild1 = new AgentTrace([
            'id' => 'grandchild1-id',
            'trace_id' => 'trace-hier',
            'parent_id' => 'child1-id',
            'name' => 'Handoff 1',
            'type' => 'handoff',
        ]);
        $grandchild1->saveQuietly();

        $child2 = new AgentTrace([
            'id' => 'child2-id',
            'trace_id' => 'trace-hier',
            'parent_id' => 'root-id',
            'name' => 'Step 1',
            'type' => 'llm_step',
        ]);
        $child2->saveQuietly();

        // Create a second unrelated trace with same trace_id
        $otherRoot = new AgentTrace([
            'id' => 'other-root-id',
            'trace_id' => 'trace-hier',
            'name' => 'Other Root',
            'type' => 'agent_execution',
        ]);
        $otherRoot->saveQuietly();

        $otherChild = new AgentTrace([
            'id' => 'other-child-id',
            'trace_id' => 'trace-hier',
            'parent_id' => 'other-root-id',
            'name' => 'Other Child',
            'type' => 'llm_step',
        ]);
        $otherChild->saveQuietly();

        // Test hierarchy building for the first root
        $hierarchy = AgentTrace::buildHierarchy('root-id');

        // Check structure - should only include traces from the first hierarchy
        $this->assertCount(4, $hierarchy);

        // Check levels
        $this->assertEquals(0, $hierarchy[0]['level']); // Root
        $this->assertEquals(1, $hierarchy[1]['level']); // Child 1
        $this->assertEquals(2, $hierarchy[2]['level']); // Grandchild
        $this->assertEquals(1, $hierarchy[3]['level']); // Child 2

        // Check ID order in the hierarchy
        $this->assertEquals('root-id', $hierarchy[0]['model']->id);
        $this->assertEquals('child1-id', $hierarchy[1]['model']->id);
        $this->assertEquals('grandchild1-id', $hierarchy[2]['model']->id);
        $this->assertEquals('child2-id', $hierarchy[3]['model']->id);

        // Check visibility flags - all should be visible now
        $this->assertTrue($hierarchy[0]['visible']); // Root is visible
        $this->assertTrue($hierarchy[1]['visible']); // Child 1 is visible
        $this->assertTrue($hierarchy[2]['visible']); // Grandchild is visible
        $this->assertTrue($hierarchy[3]['visible']); // Child 2 is visible

        // Verify the other hierarchy is not included
        $hierarchyIds = collect($hierarchy)->pluck('model.id')->toArray();
        $this->assertNotContains('other-root-id', $hierarchyIds);
        $this->assertNotContains('other-child-id', $hierarchyIds);
    }

    public function test_formatted_duration_attribute()
    {
        // Test with decimal duration
        $trace = new AgentTrace([
            'id' => 'test-id',
            'duration' => -15.75,
        ]);
        $this->assertEquals('15.75 ms', $trace->formatted_duration);

        // Test with negative duration (should use absolute value)
        $trace = new AgentTrace([
            'id' => 'test-id-2',
            'duration' => -1500,
        ]);
        $this->assertEquals('1,500.00 ms', $trace->formatted_duration);

        // Test with null duration
        $trace = new AgentTrace([
            'id' => 'test-id-3',
            'duration' => null,
        ]);
        $this->assertEquals('N/A', $trace->formatted_duration);
    }

    public function test_actual_duration_attribute()
    {
        // Test with positive duration
        $trace = new AgentTrace([
            'id' => 'test-id',
            'duration' => 15.75,
        ]);
        $this->assertEquals(15.75, $trace->actual_duration);

        // Test with negative duration (should use absolute value)
        $trace = new AgentTrace([
            'id' => 'test-id-2',
            'duration' => -10.5,
        ]);
        $this->assertEquals(10.5, $trace->actual_duration);

        // Test with null duration
        $trace = new AgentTrace([
            'id' => 'test-id-3',
            'duration' => null,
        ]);
        $this->assertEquals(0, $trace->actual_duration);
    }

    public function test_status_value_attribute()
    {
        // Test with status field
        $trace = new AgentTrace([
            'id' => 'test-id',
            'status' => 'success',
        ]);
        $this->assertEquals('success', $trace->status_value);

        // Test with status in metadata
        $trace = new AgentTrace([
            'id' => 'test-id-2',
            'metadata' => ['status' => 'error'],
        ]);
        $this->assertEquals('error', $trace->status_value);

        // Test with no status
        $trace = new AgentTrace([
            'id' => 'test-id-3',
        ]);
        $this->assertEquals('unknown', $trace->status_value);
    }
}
