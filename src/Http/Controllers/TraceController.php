<?php

namespace Grpaiva\PrismAgents\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Config;
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
        $connection = Config::get('prism-agents.tracing.connection') ?: config('database.default');
        $table = Config::get('prism-agents.tracing.table', 'prism_agent_traces');
        
        // Get distinct trace IDs with their root span
        $traces = DB::connection($connection)
            ->table($table)
            ->where('trace_id', '!=', 'agent_runner')
            ->whereNull('parent_id')
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
        $connection = Config::get('prism-agents.tracing.connection') ?: config('database.default');
        $table = Config::get('prism-agents.tracing.table', 'prism_agent_traces');
        
        // Get all spans for this trace
        $spans = DB::connection($connection)
            ->table($table)
            ->where('id', $traceId)
            ->orWhere('parent_id', $traceId)
            ->orderBy('started_at')
            ->get();
            
        if ($spans->isEmpty()) {
            abort(404, 'Trace not found');
        }
            
        // Find the root span (no parent_id)
        $rootSpan = $spans->firstWhere('parent_id', null);
        
        // Calculate total duration
        $totalDuration = $rootSpan->duration ?: 1; // Default to 1ms to avoid division by zero
        
        // Process spans to build a tree structure
        $spanTree = $this->buildSpanTree($spans);
        
        return view('prism-agents::traces.show', [
            'traceId' => $traceId,
            'spans' => $spans,
            'spanTree' => $spanTree,
            'rootSpan' => $rootSpan,
            'totalDuration' => $totalDuration,
        ]);
    }
    
    /**
     * Build a tree structure from flat spans array
     * 
     * @param \Illuminate\Support\Collection $spans
     * @return array
     */
    protected function buildSpanTree($spans)
    {
        // Index spans by ID for quick lookup
        $indexedSpans = [];
        foreach ($spans as $span) {
            $indexedSpans[$span->id] = [
                'span' => $span,
                'children' => [],
            ];
        }
        
        // Build the tree
        $tree = [];
        foreach ($spans as $span) {
            if ($span->parent_id === null) {
                // This is a root span
                $tree[] = &$indexedSpans[$span->id];
            } else {
                // This span has a parent
                if (isset($indexedSpans[$span->parent_id])) {
                    $indexedSpans[$span->parent_id]['children'][] = &$indexedSpans[$span->id];
                }
            }
        }
        
        return $tree;
    }
} 