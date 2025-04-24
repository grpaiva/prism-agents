<?php

namespace Grpaiva\PrismAgents\Http\Controllers;

use Grpaiva\PrismAgents\Models\AgentTrace;
use Grpaiva\PrismAgents\Models\AgentSpan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TraceController extends Controller
{
    /**
     * Display a listing of traces
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $traces = AgentTrace::orderBy('created_at', 'desc')
            ->paginate(20);
        
        return view('prism-agents::traces.index', [
            'traces' => $traces,
        ]);
    }
    
    /**
     * Display a specific trace
     * 
     * @param Request $request
     * @param string $traceId
     * @return \Illuminate\View\View
     */
    public function show(Request $request, string $traceId)
    {
        // Get the trace by ID - add prefix if not present
        if (strpos($traceId, 'trace_') !== 0) {
            $traceId = 'trace_' . $traceId;
        }
        
        $trace = AgentTrace::findTrace($traceId);
        
        if (!$trace) {
            abort(404, 'Trace not found');
        }
        
        // Calculate total duration
        $totalDuration = $trace->duration_ms ?: 1; // Default to 1ms to avoid division by zero
        
        // Build the hierarchical span structure
        $hierarchicalSpans = $trace->getFlattenedSpanHierarchy();
        
        return view('prism-agents::traces.show', [
            'traceId' => $traceId,
            'trace' => $trace,
            'hierarchicalSpans' => $hierarchicalSpans,
            'totalDuration' => $totalDuration,
        ]);
    }
    
    /**
     * API endpoint to get trace data
     * 
     * @param Request $request
     * @param string $traceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiGetTrace(Request $request, string $traceId)
    {
        // Get the trace by ID - add prefix if not present
        if (strpos($traceId, 'trace_') !== 0) {
            $traceId = 'trace_' . $traceId;
        }
        
        $trace = AgentTrace::findTrace($traceId);
        
        if (!$trace) {
            return response()->json([
                'error' => 'Trace not found'
            ], 404);
        }
        
        return response()->json([
            'id' => $trace->id,
            'object' => $trace->object,
            'created_at' => $trace->created_at,
            'duration_ms' => $trace->duration_ms,
            'first_5_agents' => $trace->first_5_agents,
            'group_id' => $trace->group_id,
            'handoff_count' => $trace->handoff_count,
            'tool_count' => $trace->tool_count,
            'workflow_name' => $trace->workflow_name,
            'metadata' => $trace->metadata
        ]);
    }
    
    /**
     * API endpoint to get spans for a trace
     * 
     * @param Request $request
     * @param string $traceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiGetSpans(Request $request, string $traceId)
    {
        // Get the trace by ID - add prefix if not present
        if (strpos($traceId, 'trace_') !== 0) {
            $traceId = 'trace_' . $traceId;
        }
        
        $trace = AgentTrace::findTrace($traceId);
        
        if (!$trace) {
            return response()->json([
                'error' => 'Trace not found'
            ], 404);
        }
        
        // Pagination parameters
        $limit = $request->input('limit', 50);
        $after = $request->input('after');
        $before = $request->input('before');
        
        // Build query for spans
        $spansQuery = AgentSpan::where('trace_id', $traceId)
            ->orderBy('started_at');
            
        if ($after) {
            $spansQuery->where('id', '>', $after);
        }
        
        if ($before) {
            $spansQuery->where('id', '<', $before);
        }
        
        $spans = $spansQuery->limit($limit + 1)->get();
        
        // Check if there are more results
        $hasMore = $spans->count() > $limit;
        if ($hasMore) {
            $spans->pop(); // Remove the extra item
        }
        
        // Format the spans for the API response
        $formattedSpans = $spans->map(function($span) {
            return [
                'id' => $span->id,
                'object' => $span->object,
                'created_at' => $span->created_at,
                'duration_ms' => $span->duration_ms,
                'ended_at' => $span->ended_at,
                'error' => $span->error,
                'parent_id' => $span->parent_id,
                'span_data' => $span->span_data,
                'speech_group_output' => $span->speech_group_output,
                'started_at' => $span->started_at,
                'trace_id' => $span->trace_id
            ];
        });
        
        return response()->json([
            'object' => 'list',
            'data' => $formattedSpans,
            'first_id' => $spans->first()?->id,
            'last_id' => $spans->last()?->id,
            'has_more' => $hasMore
        ]);
    }
} 