<?php

namespace Grpaiva\PrismAgents\Http\Controllers;

use Grpaiva\PrismAgents\Models\AgentTrace;
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
        $traces = AgentTrace::root()
            ->where('trace_id', '!=', 'agent_runner')
            ->orderBy('started_at', 'desc')
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
        // Get the root trace by ID
        $rootSpan = AgentTrace::find($traceId);
        
        if (!$rootSpan) {
            abort(404, 'Trace not found');
        }
        
        // Calculate total duration
        $totalDuration = $rootSpan->actual_duration ?: 1; // Default to 1ms to avoid division by zero
        
        // Build the hierarchical trace structure starting from this specific trace
        $hierarchicalTraces = AgentTrace::buildHierarchy($traceId);
        
        return view('prism-agents::traces.show', [
            'traceId' => $traceId,
            'rootSpan' => $rootSpan,
            'hierarchicalTraces' => $hierarchicalTraces,
            'totalDuration' => $totalDuration,
        ]);
    }
} 