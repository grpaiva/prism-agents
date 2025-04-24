@extends('prism-agents::layouts.app')

@section('title', 'Traces')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-2xl font-semibold text-gray-900">Traces</h1>
    <div class="relative">
        <input type="text" id="trace-search" placeholder="Search traces by ID or workflow..." class="w-80 pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
    </div>
</div>

<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <div class="grid grid-cols-12 text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">
        <div class="col-span-3 px-4 py-3">Workflow</div>
        <div class="col-span-3 px-4 py-3">Flow</div>
        <div class="col-span-1 px-4 py-3 text-center">Handoffs</div>
        <div class="col-span-1 px-4 py-3 text-center">Tools</div>
        <div class="col-span-2 px-4 py-3">Execution time</div> 
        <div class="col-span-2 px-4 py-3">Created</div>
    </div>

    <div class="divide-y divide-gray-200">
        @forelse ($traces as $trace)
            <a href="{{ route('prism-agents.traces.show', $trace->id) }}" class="grid grid-cols-12 hover:bg-gray-50 transition-colors duration-150">
                <div class="col-span-3 px-4 py-3">
                    <div class="text-sm font-medium text-indigo-600 truncate">
                        {{ $trace->workflow_name ?? 'Unknown Workflow' }}
                    </div>
                    <div class="text-xs text-gray-500 truncate mt-1">
                        {{ substr($trace->id, 0, 10) }}...
                    </div>
                </div>
                
                <div class="col-span-3 px-4 py-3">
                    <div class="flex items-center space-x-1">
                        @if(isset($trace->first_5_agents) && count($trace->first_5_agents) > 0)
                            @foreach(array_slice($trace->first_5_agents, 0, 2) as $index => $agentName)
                                <div class="text-sm text-gray-900 font-medium">{{ $agentName }}</div>
                                
                                @if($index < min(1, count($trace->first_5_agents) - 1))
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                @endif
                            @endforeach
                            
                            @if(count($trace->first_5_agents) > 2)
                                <span class="text-xs text-gray-500">+{{ count($trace->first_5_agents) - 2 }} more</span>
                            @endif
                        @else
                            <span class="text-sm text-gray-500">No agents</span>
                        @endif
                    </div>
                </div>
                
                <div class="col-span-1 px-4 py-3 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        {{ $trace->handoff_count ?? 0 }}
                    </span>
                </div>
                
                <div class="col-span-1 px-4 py-3 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ $trace->tool_count ?? 0 }}
                    </span>
                </div>
                
                <div class="col-span-2 px-4 py-3">
                    <span class="text-sm text-gray-900">
                        {{ $trace->formatted_duration }}
                    </span>
                </div>
                
                <div class="col-span-2 px-4 py-3">
                    <span class="text-sm text-gray-900">
                        {{ $trace->created_at->format('M j, Y, g:i A') }}
                    </span>
                </div>
            </a>
        @empty
            <div class="text-center py-6 text-gray-500">
                No traces found
            </div>
        @endforelse
    </div>
</div>

<div class="mt-4">
    {{ $traces->links() }}
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('trace-search').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            const searchValue = this.value.trim();
            if (searchValue) {
                window.location.href = '{{ route("prism-agents.traces.index") }}?search=' + encodeURIComponent(searchValue);
            }
        }
    });
</script>
@endsection 