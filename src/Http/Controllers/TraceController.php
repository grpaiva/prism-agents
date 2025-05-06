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
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get per page setting, default to 100
        $perPage = (int) $request->input('per_page', 100);

        // Limit to reasonable values
        $perPage = max(10, min(500, $perPage));

        // Sort field and direction
        $sortField = $request->input('sort', 'started_at');
        $sortDirection = $request->input('direction', 'desc');

        // Validate sort field to prevent SQL injection
        $allowedSortFields = ['started_at', 'created_at', 'name', 'type', 'duration', 'status'];
        if (! in_array($sortField, $allowedSortFields)) {
            $sortField = 'started_at';
        }

        // Validate sort direction
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        $traces = AgentTrace::root()
            ->where('trace_id', '!=', 'agent_runner')
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString(); // Preserve other query parameters

        return view('prism-agents::traces.index', [
            'traces' => $traces,
            'perPage' => $perPage,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection,
        ]);
    }

    /**
     * Display a specific trace
     *
     * @return \Illuminate\View\View
     */
    public function show(Request $request, string $traceId)
    {
        // Get the root trace by ID
        $rootSpan = AgentTrace::find($traceId);

        if (! $rootSpan) {
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
