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
                    <h3 class="text-sm font-medium text-gray-900">Flow</h3>
                </div>
                <div class="ml-8 flex-shrink-0 flex">
                    <h3 class="text-sm font-medium text-gray-900">Handoffs</h3>
                </div>
                <div class="ml-8 flex-shrink-0 flex">
                    <h3 class="text-sm font-medium text-gray-900">Tools</h3>
                </div>
                <div class="ml-8 flex-shrink-0 flex">
                    <h3 class="text-sm font-medium text-gray-900">Execution time</h3>
                </div>
                <div class="ml-8 flex-shrink-0 flex">
                    <h3 class="text-sm font-medium text-gray-900">Created</h3>
                </div>
            </div>
        </div>
        
        <ul class="divide-y divide-gray-200">
            @forelse($workflowGroups as $workflow)
                <li>
                    <a href="{{ route('prism-agents.traces.show', $workflow['parent']->id) }}" class="block hover:bg-gray-50">
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex items-center">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-indigo-600 truncate flex items-center">
                                        <span class="inline-block w-3 h-3 bg-purple-500 rounded-full mr-2"></span>
                                        {{ $workflow['parent']->name }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 truncate">
                                        {{ $workflow['parent']->id }}
                                    </p>
                                </div>
                                <div class="ml-2 flex-shrink-0 flex">
                                    <p class="text-sm text-gray-500">
                                        @php
                                            $flowAgents = $workflow['children']->pluck('name')->toArray();
                                            echo empty($flowAgents) ? 'N/A' : implode(' → ', $flowAgents);
                                        @endphp
                                    </p>
                                </div>
                                <div class="ml-8 flex-shrink-0 flex">
                                    <p class="text-sm text-gray-500 text-center w-8">
                                        {{ $workflow['parent']->handoff_count }}
                                    </p>
                                </div>
                                <div class="ml-8 flex-shrink-0 flex">
                                    <p class="text-sm text-gray-500 text-center w-8">
                                        {{ $workflow['parent']->tool_count }}
                                    </p>
                                </div>
                                <div class="ml-8 flex-shrink-0 flex">
                                    <p class="text-sm text-gray-500">
                                        {{ $workflow['parent']->formatted_duration }}
                                    </p>
                                </div>
                                <div class="ml-8 flex-shrink-0 flex">
                                    <p class="text-sm text-gray-500">
                                        {{ $workflow['parent']->started_at->format('M j, Y, g:i A') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </a>
                </li>
                
                @foreach($workflow['children'] as $child)
                    <li>
                        <a href="{{ route('prism-agents.traces.show', $child->id) }}" class="block hover:bg-gray-50 pl-8 border-l-4 border-indigo-100">
                            <div class="px-4 py-3 sm:px-6">
                                <div class="flex items-center">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-600 truncate flex items-center">
                                            <span class="inline-block w-2 h-2 bg-indigo-400 rounded-full mr-2"></span>
                                            {{ $child->name }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500 truncate">
                                            {{ $child->id }}
                                        </p>
                                    </div>
                                    <div class="ml-2 flex-shrink-0 flex">
                                        <p class="text-sm text-gray-500">
                                            <!-- Child executions don't have flow -->
                                        </p>
                                    </div>
                                    <div class="ml-8 flex-shrink-0 flex">
                                        <p class="text-sm text-gray-500 text-center w-8">
                                            {{ $child->handoff_count }}
                                        </p>
                                    </div>
                                    <div class="ml-8 flex-shrink-0 flex">
                                        <p class="text-sm text-gray-500 text-center w-8">
                                            {{ $child->tool_count }}
                                        </p>
                                    </div>
                                    <div class="ml-8 flex-shrink-0 flex">
                                        <p class="text-sm text-gray-500">
                                            {{ $child->formatted_duration }}
                                        </p>
                                    </div>
                                    <div class="ml-8 flex-shrink-0 flex">
                                        <p class="text-sm text-gray-500">
                                            {{ $child->started_at->format('M j, Y, g:i A') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            @empty
                <li class="px-4 py-5 sm:px-6 text-center text-sm text-gray-500">
                    No traces found. Run some agent operations to generate traces.
                </li>
            @endforelse
        </ul>
        
        <div class="px-4 py-3 bg-white border-t border-gray-200 sm:px-6">
            {{ $executions->links() }}
        </div>
    </div>
</div>
@endsection 