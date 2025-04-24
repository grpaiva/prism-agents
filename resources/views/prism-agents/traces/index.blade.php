@extends('prism-agents::layouts.app')

@section('title', 'Traces')

@section('content')
<div class="bg-white shadow sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <h2 class="text-lg font-medium text-gray-900">Traces</h2>
        <p class="mt-1 text-sm text-gray-500">View execution traces of your agents and their tools.</p>
    </div>
    
    <div class="border-t border-gray-200">
        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 sm:px-6">
            <div class="flex items-center">
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-medium text-gray-900">Workflow</h3>
                </div>
                <div class="ml-2 flex-shrink-0 flex">
                    <h3 class="text-sm font-medium text-gray-900">Created</h3>
                </div>
                <div class="ml-8 flex-shrink-0 flex">
                    <h3 class="text-sm font-medium text-gray-900">Duration</h3>
                </div>
                <div class="ml-8 flex-shrink-0 flex">
                    <h3 class="text-sm font-medium text-gray-900">Handoffs</h3>
                </div>
                <div class="ml-8 flex-shrink-0 flex">
                    <h3 class="text-sm font-medium text-gray-900">Tools</h3>
                </div>
                <div class="ml-8 flex-shrink-0 flex">
                    <h3 class="text-sm font-medium text-gray-900">Agents</h3>
                </div>
            </div>
        </div>
        
        @forelse($traces as $trace)
            <div class="hover:bg-gray-50">
                <a href="{{ route('prism-agents.traces.show', $trace->id) }}" class="block">
                    <div class="px-4 py-4 sm:px-6">
                        <div class="flex items-center">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center">
                                    <div>
                                        <p class="text-sm font-medium text-indigo-600 truncate">
                                            {{ $trace->workflow_name ?? substr($trace->id, 6) }}
                                        </p>
                                        <p class="mt-1 text-sm text-gray-500 truncate">
                                            {{ $trace->id }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="ml-2 flex-shrink-0 flex">
                                <p class="px-2 text-sm text-gray-700">
                                    {{ $trace->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="ml-8 flex-shrink-0 flex">
                                <p class="px-2 text-sm text-gray-700">
                                    {{ $trace->formatted_duration }}
                                </p>
                            </div>
                            <div class="ml-8 flex-shrink-0 flex">
                                <p class="px-2 text-sm text-gray-700">
                                    {{ $trace->handoff_count ?? 'N/A' }}
                                </p>
                            </div>
                            <div class="ml-8 flex-shrink-0 flex">
                                <p class="px-2 text-sm text-gray-700">
                                    {{ $trace->tool_count ?? 'N/A' }}
                                </p>
                            </div>
                            <div class="ml-8 flex-shrink-0 flex">
                                <div class="px-2 text-sm text-gray-700">
                                    @foreach($trace->first_5_agents as $agent)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-1">
                                            {{ $agent }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="px-4 py-5 sm:px-6 text-center">
                <p class="text-gray-500">No traces found</p>
            </div>
        @endforelse
        
        <div class="px-4 py-3 bg-white border-t border-gray-200 sm:px-6">
            {{ $traces->links() }}
        </div>
    </div>
</div>
@endsection 