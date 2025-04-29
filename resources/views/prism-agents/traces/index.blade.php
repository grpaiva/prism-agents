@extends('prism-agents::layouts.app')

@section('title', 'Traces')

@section('content')
<div x-data="{
    selectedType: 'all',
    filterText: '',
    getStatusColor(status) {
        switch(status) {
            case 'success':
                return 'bg-green-100 text-green-800';
            case 'error':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    },
    typeToIcon(type) {
        switch(type) {
            case 'agent_execution':
                return '🤖';
            case 'agent_run':
                return '🧠';
            default:
                return '📋';
        }
    },
    getBadgeColorByType(type) {
        switch(type) {
            case 'agent_execution':
                return 'bg-blue-100 text-blue-800';
            case 'agent_run':
                return 'bg-indigo-100 text-indigo-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    },
    isVisible(trace) {
        if (this.selectedType !== 'all' && trace.type !== this.selectedType) {
            return false;
        }
        
        if (this.filterText.trim() === '') {
            return true;
        }
        
        const searchText = this.filterText.toLowerCase();
        return trace.name.toLowerCase().includes(searchText) || 
               trace.trace_id.toLowerCase().includes(searchText) ||
               (trace.agent_name && trace.agent_name.toLowerCase().includes(searchText));
    }
}">
    <div class="bg-white shadow sm:rounded-lg mb-6">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Agent Traces</h2>
                <p class="mt-1 text-sm text-gray-500">View and debug agent execution traces</p>
            </div>
            
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <input 
                        type="text" 
                        x-model="filterText" 
                        placeholder="Filter traces..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    >
                </div>
                
                <select 
                    x-model="selectedType"
                    class="block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                >
                    <option value="all">All Types</option>
                    <option value="agent_execution">Agent Execution</option>
                    <option value="agent_run">Agent Run</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="bg-white shadow sm:rounded-lg">
        <div class="overflow-hidden border-b border-gray-200 sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Agent/Name
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Type
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Started
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Duration
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Handoffs/Tools
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($traces as $trace)
                        <tr 
                            x-data="{ trace: {
                                name: '{{ $trace->name }}',
                                type: '{{ $trace->type }}',
                                trace_id: '{{ $trace->trace_id }}',
                                agent_name: '{{ $trace->agent_name }}'
                            }}"
                            x-show="isVisible(trace)"
                            class="hover:bg-gray-50 transition duration-150"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-full" :class="getBadgeColorByType('{{ $trace->type }}')">
                                        <span class="text-lg" x-text="typeToIcon('{{ $trace->type }}')"></span>
                                    </div>
                                    <div class="ml-4">
                                        <a href="{{ route('prism-agents.traces.show', $trace->id) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900 truncate max-w-xs block">
                                            {{ $trace->name }}
                                        </a>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs text-gray-500 font-mono truncate max-w-xs">
                                                {{ $trace->trace_id }}
                                            </span>
                                            @if($trace->agent_name)
                                                <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-50 text-indigo-700">
                                                    {{ $trace->agent_name }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-medium rounded-full" :class="getBadgeColorByType('{{ $trace->type }}')">
                                    {{ $trace->type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ $trace->started_at->format('M j, Y') }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $trace->started_at->format('g:i:s A') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $trace->formatted_duration }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex space-x-4">
                                    <div class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-orange-500 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="text-sm">{{ $trace->handoff_count ?: 0 }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-purple-500 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="text-sm">{{ $trace->tool_call_count ?: 0 }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $status = $trace->status_value;
                                @endphp
                                <span 
                                    class="px-2 py-1 inline-flex text-xs leading-5 font-medium rounded-full"
                                    :class="getStatusColor('{{ $status }}')"
                                >
                                    {{ ucfirst($status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <p>No traces found. Run some agent operations to generate traces.</p>
                                    <p class="mt-2 text-xs text-gray-400">Traces are automatically created when agents execute.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            
            <div class="px-6 py-3 bg-white border-t border-gray-200">
                {{ $traces->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

[x-cloak] { display: none !important; }
[x-show] { animation: fadeIn 0.2s ease-out; }
</style>
@endpush 