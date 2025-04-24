@extends('prism-agents::layouts.app')

@section('title', 'Trace Details')

@section('content')
<div x-data="{
    selectedSpanId: null,
    selectedSpan: null,
    traces: {{ json_encode($spans) }},
    totalDuration: {{ $totalDuration }},
    formatDuration(ms) {
        return Math.abs(ms).toFixed(2) + ' ms';
    },
    setSelectedSpan(span) {
        this.selectedSpanId = span.id;
        this.selectedSpan = span;
        console.log('Selected Span:', span);
    },
    getPercentage(duration) {
        return Math.min(100, Math.max(0, (Math.abs(duration) / this.totalDuration) * 100));
    },
    getDisplayTime(span) {
        const startTime = new Date(span.started_at).getTime();
        const endTime = span.ended_at ? new Date(span.ended_at).getTime() : startTime;
        const duration = Math.abs(endTime - startTime);
        return this.formatDuration(duration);
    },
    typeToIcon(type) {
        switch(type) {
            case 'agent_execution':
                return '🤖';
            case 'agent_run':
                return '🧠';
            case 'llm_step':
                return '💬';
            case 'handoff':
                return '🔄';
            case 'tool_call':
                return '🔧';
            default:
                return '📋';
        }
    }
}"
class="space-y-6">

    <!-- Breadcrumb and trace info -->
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-medium text-gray-900 flex items-center">
                        <a href="{{ route('prism-agents.traces.index') }}" class="text-indigo-600 hover:text-indigo-900 mr-2">
                            Traces
                        </a>
                        <svg class="h-4 w-4 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        {{ $rootSpan->name }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $traceId }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-medium text-gray-900">Started</p>
                    <p class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($rootSpan->started_at)->format('M j, Y g:i:s A') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Trace visualization -->
    <div class="flex space-x-4">
        <!-- Trace tree -->
        <div class="w-2/3 bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Execution Timeline</h3>
                <p class="mt-1 text-sm text-gray-500">Trace duration: <span x-text="formatDuration(totalDuration)"></span></p>
            </div>
            
            <div class="divide-y divide-gray-200">
                <template x-for="(span, index) in traces" :key="span.id">
                    <div 
                        class="px-4 py-3 sm:px-6 cursor-pointer hover:bg-gray-50"
                        :class="{ 'bg-blue-50': selectedSpanId === span.id }"
                        @click="setSelectedSpan(span)"
                    >
                        <div class="flex items-center space-x-3">
                            <div class="w-7 text-center">
                                <span x-text="typeToIcon(span.type)"></span>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-900" x-text="span.name"></span>
                                    <span class="text-sm text-gray-500" x-text="getDisplayTime(span)"></span>
                                </div>
                                
                                <div class="mt-1 timeline-bar" :class="span.type">
                                    <div 
                                        class="timeline-progress" 
                                        :style="{ width: getPercentage(span.duration) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        
        <!-- Span details -->
        <div class="w-1/3 bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Details</h3>
                <p class="mt-1 text-sm text-gray-500">Select a span to view details</p>
            </div>
            
            <div class="px-4 py-5 sm:px-6 space-y-4" x-cloak x-show="selectedSpan">
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Span Type</h4>
                    <p class="mt-1 text-sm text-gray-900" x-text="selectedSpan?.type"></p>
                </div>
                
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Span ID</h4>
                    <p class="mt-1 text-sm text-gray-900 break-all" x-text="selectedSpan?.id"></p>
                </div>
                
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Started At</h4>
                    <p class="mt-1 text-sm text-gray-900" x-text="new Date(selectedSpan?.started_at).toLocaleString()"></p>
                </div>
                
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Duration</h4>
                    <p class="mt-1 text-sm text-gray-900" x-text="formatDuration(selectedSpan?.duration)"></p>
                </div>
                
                <template x-if="selectedSpan && selectedSpan.metadata">
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 mb-2">Metadata</h4>
                        
                        <div class="border border-gray-200 rounded-md">
                            <div x-data="{ tab: 'basic' }" class="bg-gray-50 p-1 rounded-t-md border-b border-gray-200">
                                <div class="flex space-x-2 text-xs">
                                    <button 
                                        @click="tab = 'basic'" 
                                        :class="{ 'bg-white shadow text-gray-900': tab === 'basic', 'text-gray-500 hover:text-gray-700': tab !== 'basic' }"
                                        class="px-3 py-1 rounded-md"
                                    >
                                        Basic
                                    </button>
                                    <button 
                                        @click="tab = 'system'" 
                                        :class="{ 'bg-white shadow text-gray-900': tab === 'system', 'text-gray-500 hover:text-gray-700': tab !== 'system' }"
                                        class="px-3 py-1 rounded-md"
                                        x-show="JSON.parse(selectedSpan.metadata).system_message"
                                    >
                                        System Message
                                    </button>
                                    <button 
                                        @click="tab = 'raw'" 
                                        :class="{ 'bg-white shadow text-gray-900': tab === 'raw', 'text-gray-500 hover:text-gray-700': tab !== 'raw' }"
                                        class="px-3 py-1 rounded-md"
                                    >
                                        Raw JSON
                                    </button>
                                </div>
                            </div>
                            
                            <div class="p-3 overflow-auto max-h-96 text-xs font-mono">
                                <div x-show="tab === 'basic'">
                                    <template x-if="JSON.parse(selectedSpan.metadata).agent">
                                        <div class="mb-2">
                                            <span class="text-gray-500">Agent:</span>
                                            <span class="text-gray-900" x-text="JSON.parse(selectedSpan.metadata).agent"></span>
                                        </div>
                                    </template>
                                    
                                    <template x-if="JSON.parse(selectedSpan.metadata).provider">
                                        <div class="mb-2">
                                            <span class="text-gray-500">Provider:</span>
                                            <span class="text-gray-900" x-text="JSON.parse(selectedSpan.metadata).provider"></span>
                                        </div>
                                    </template>
                                    
                                    <template x-if="JSON.parse(selectedSpan.metadata).model">
                                        <div class="mb-2">
                                            <span class="text-gray-500">Model:</span>
                                            <span class="text-gray-900" x-text="JSON.parse(selectedSpan.metadata).model"></span>
                                        </div>
                                    </template>
                                    
                                    <template x-if="JSON.parse(selectedSpan.metadata).input">
                                        <div class="mb-2">
                                            <div class="text-gray-500">Input:</div>
                                            <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="JSON.parse(selectedSpan.metadata).input"></div>
                                        </div>
                                    </template>
                                    
                                    <template x-if="JSON.parse(selectedSpan.metadata).output">
                                        <div class="mb-2">
                                            <div class="text-gray-500">Output:</div>
                                            <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="JSON.parse(selectedSpan.metadata).output"></div>
                                        </div>
                                    </template>
                                    
                                    <template x-if="JSON.parse(selectedSpan.metadata).status">
                                        <div class="mb-2">
                                            <span class="text-gray-500">Status:</span>
                                            <span 
                                                :class="{
                                                    'text-green-600': JSON.parse(selectedSpan.metadata).status === 'success',
                                                    'text-red-600': JSON.parse(selectedSpan.metadata).status === 'error',
                                                    'text-gray-900': !['success', 'error'].includes(JSON.parse(selectedSpan.metadata).status)
                                                }"
                                                x-text="JSON.parse(selectedSpan.metadata).status"
                                            ></span>
                                        </div>
                                    </template>
                                    
                                    <template x-if="JSON.parse(selectedSpan.metadata).tool_name">
                                        <div class="mb-2">
                                            <div class="text-gray-500">Tool:</div>
                                            <div class="text-gray-900" x-text="JSON.parse(selectedSpan.metadata).tool_name"></div>
                                        </div>
                                    </template>
                                    
                                    <template x-if="JSON.parse(selectedSpan.metadata).args">
                                        <div class="mb-2">
                                            <div class="text-gray-500">Arguments:</div>
                                            <pre class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="JSON.stringify(JSON.parse(selectedSpan.metadata).args, null, 2)"></pre>
                                        </div>
                                    </template>
                                    
                                    <template x-if="JSON.parse(selectedSpan.metadata).result">
                                        <div class="mb-2">
                                            <div class="text-gray-500">Result:</div>
                                            <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="JSON.parse(selectedSpan.metadata).result"></div>
                                        </div>
                                    </template>
                                </div>
                                
                                <div x-show="tab === 'system'" x-cloak>
                                    <template x-if="JSON.parse(selectedSpan.metadata).system_message">
                                        <div>
                                            <div class="mb-3">
                                                <div class="text-gray-500 font-bold">Default SDK Message:</div>
                                                <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="JSON.parse(selectedSpan.metadata).system_message.default"></div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="text-gray-500 font-bold">Agent Instructions:</div>
                                                <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="JSON.parse(selectedSpan.metadata).system_message.agent_instructions"></div>
                                            </div>
                                            
                                            <div>
                                                <div class="text-gray-500 font-bold">Combined Message:</div>
                                                <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="JSON.parse(selectedSpan.metadata).system_message.combined"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                
                                <div x-show="tab === 'raw'" x-cloak>
                                    <pre x-text="JSON.stringify(JSON.parse(selectedSpan.metadata), null, 2)"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            
            <div class="px-4 py-5 sm:px-6" x-cloak x-show="!selectedSpan">
                <p class="text-sm text-gray-500">Click on a span in the timeline to view its details</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Auto-select the root span on load
    const alpineData = Alpine.data('root');
    const rootSpan = alpineData.traces.find(span => span.parent_id === null);
    if (rootSpan) {
        alpineData.setSelectedSpan(rootSpan);
    }
});
</script>
@endsection 