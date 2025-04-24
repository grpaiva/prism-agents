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
                    <h3 class="text-sm font-medium text-gray-900">Agent</h3>
                </div>
                <div class="ml-2 flex-shrink-0 flex">
                    <h3 class="text-sm font-medium text-gray-900">Started</h3>
                </div>
                <div class="ml-8 flex-shrink-0 flex">
                    <h3 class="text-sm font-medium text-gray-900">Duration</h3>
                </div>
                <div class="ml-8 flex-shrink-0 flex">
                    <h3 class="text-sm font-medium text-gray-900">Status</h3>
                </div>
            </div>
        </div>
        
        <ul class="divide-y divide-gray-200">
            @forelse($traces as $trace)
                <li>
                    <a href="{{ route('prism-agents.traces.show', $trace->id) }}" class="block hover:bg-gray-50">
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex items-center">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-indigo-600 truncate">
                                        {{ $trace->name }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 truncate">
                                        {{ $trace->trace_id }}
                                    </p>
                                </div>
                                <div class="ml-2 flex-shrink-0 flex">
                                    <p class="text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($trace->started_at)->format('M j, Y g:i:s A') }}
                                    </p>
                                </div>
                                <div class="ml-8 flex-shrink-0 flex">
                                    <p class="text-sm text-gray-500">
                                        {{ abs($trace->duration) }} ms
                                    </p>
                                </div>
                                <div class="ml-8 flex-shrink-0 flex">
                                    @php
                                        $metadata = json_decode($trace->metadata, true);
                                        $status = $metadata['status'] ?? 'unknown';
                                    @endphp
                                    
                                    @if($status === 'success')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Success
                                        </span>
                                    @elseif($status === 'error')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Error
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            {{ ucfirst($status) }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </a>
                </li>
            @empty
                <li class="px-4 py-5 sm:px-6 text-center text-sm text-gray-500">
                    No traces found. Run some agent operations to generate traces.
                </li>
            @endforelse
        </ul>
        
        <div class="px-4 py-3 bg-white border-t border-gray-200 sm:px-6">
            {{ $traces->links() }}
        </div>
    </div>
</div>
@endsection 