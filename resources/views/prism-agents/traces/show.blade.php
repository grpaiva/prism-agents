@extends('prism-agents::layouts.app')

@section('title', 'Trace Details')

@section('content')
<div x-data="{
    tab: 'basic',
    selectedSpanId: null,
    selectedSpan: null,
    hierarchicalTraces: {{ json_encode($hierarchicalTraces) }},
    totalDuration: {{ $totalDuration }},
    formatDuration(ms) {
        return Number(Math.abs(ms)).toFixed(2) + ' ms';
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
        if (span.duration !== null) {
            return this.formatDuration(Math.abs(span.duration));
        }
        return 'N/A';
    },
    hasHandoffs(span) {
        return span.type === 'llm_step' && span.metadata && span.metadata.tools && span.metadata.tools.length > 0;
    },
    getHandoffCount(span) {
        return span.type === 'llm_step' && span.metadata && span.metadata.tools ? span.metadata.tools.length : 0;
    },
    getStepName(span) {
        if (span.type === 'llm_step' && span.metadata && span.metadata.step_index !== undefined) {
            return `step_${span.metadata.step_index}`;
        }
        return span.name;
    },
    getHandoffName(span) {
        if (span.type === 'handoff' && span.metadata && span.metadata.tool_name) {
            return span.metadata.tool_name;
        }
        return span.name;
    },
    isHandoff(span) {
        return span.type === 'handoff';
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
            // arrow next
                return '→';
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
                    <p class="text-sm text-gray-500">{{ $rootSpan->started_at->format('M j, Y g:i:s A') }}</p>
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
                <template x-for="(traceItem, index) in hierarchicalTraces" :key="traceItem.model.id">
                    <div 
                        class="px-4 py-3 sm:px-6 cursor-pointer hover:bg-gray-50"
                        :class="{ 
                            'bg-blue-50': selectedSpanId === traceItem.model.id
                        }"
                        @click.stop="setSelectedSpan(traceItem.model)"
                    >
                        <div class="flex items-center">
                            <!-- Indentation based on level -->
                            <div :style="{ width: (traceItem.level * 20) + 'px' }" class="flex-shrink-0"></div>
                            
                            <!-- Type icon -->
                            <div class="w-7 text-center ml-5">
                                <span x-text="typeToIcon(traceItem.model.type)"></span>
                            </div>
                            
                            <div class="flex-1 pl-2">
                                <div class="flex justify-between">
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-900" x-text="isHandoff(traceItem.model) ? getHandoffName(traceItem.model) : getStepName(traceItem.model)"></span>
                                        
                                        <!-- Handoff indicator for steps with handoffs -->
                                        <template x-if="traceItem.model.type === 'llm_step' && getHandoffCount(traceItem.model) > 0">
                                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <template x-if="getHandoffCount(traceItem.model) === 1">
                                                    <span>1 handoff</span>
                                                </template>
                                                <template x-if="getHandoffCount(traceItem.model) > 1">
                                                    <span x-text="getHandoffCount(traceItem.model) + ' handoffs'"></span>
                                                </template>
                                            </span>
                                        </template>

                                        <!-- Tool result for handoffs -->
                                        <template x-if="isHandoff(traceItem.model) && traceItem.model.metadata && traceItem.model.metadata.result">
                                            <span class="ml-2 text-xs text-gray-500 italic truncate max-w-[200px]" x-text="'→ ' + traceItem.model.metadata.result"></span>
                                        </template>
                                    </div>
                                    <span class="text-sm text-gray-500" x-text="getDisplayTime(traceItem.model)"></span>
                                </div>
                                
                                <div class="mt-1 timeline-bar" :class="traceItem.model.type">
                                    <div 
                                        class="timeline-progress" 
                                        :style="{ width: getPercentage(traceItem.model.duration) + '%' }"
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
                            <div class="bg-gray-50 p-1 rounded-t-md border-b border-gray-200">
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
                                        x-show="selectedSpan.metadata && selectedSpan.metadata.system_message"
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
                                    <template x-if="selectedSpan.metadata && selectedSpan.metadata.agent">
                                        <div class="mb-2">
                                            <span class="text-gray-500">Agent:</span>
                                            <span class="text-gray-900" x-text="selectedSpan.metadata.agent"></span>
                                        </div>
                                    </template>
                                    
                                    <template x-if="selectedSpan.metadata && selectedSpan.metadata.provider">
                                        <div class="mb-2">
                                            <span class="text-gray-500">Provider:</span>
                                            <span class="text-gray-900" x-text="selectedSpan.metadata.provider"></span>
                                        </div>
                                    </template>
                                    
                                    <template x-if="selectedSpan.metadata && selectedSpan.metadata.model">
                                        <div class="mb-2">
                                            <span class="text-gray-500">Model:</span>
                                            <span class="text-gray-900" x-text="selectedSpan.metadata.model"></span>
                                        </div>
                                    </template>
                                    
                                    <template x-if="selectedSpan.metadata && selectedSpan.metadata.input">
                                        <div class="mb-2">
                                            <div class="text-gray-500">Input:</div>
                                            <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="selectedSpan.metadata.input"></div>
                                        </div>
                                    </template>
                                    
                                    <template x-if="selectedSpan.metadata && selectedSpan.metadata.output">
                                        <div class="mb-2">
                                            <div class="text-gray-500">Output:</div>
                                            <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="selectedSpan.metadata.output"></div>
                                        </div>
                                    </template>
                                    
                                    <template x-if="selectedSpan.metadata && selectedSpan.metadata.status">
                                        <div class="mb-2">
                                            <span class="text-gray-500">Status:</span>
                                            <span 
                                                :class="{
                                                    'text-green-600': selectedSpan.metadata.status === 'success',
                                                    'text-red-600': selectedSpan.metadata.status === 'error',
                                                    'text-gray-900': !['success', 'error'].includes(selectedSpan.metadata.status)
                                                }"
                                                x-text="selectedSpan.metadata.status"
                                            ></span>
                                        </div>
                                    </template>
                                    
                                    <template x-if="selectedSpan.metadata && selectedSpan.metadata.tool_name">
                                        <div class="mb-2">
                                            <div class="text-gray-500">Tool:</div>
                                            <div class="text-gray-900" x-text="selectedSpan.metadata.tool_name"></div>
                                        </div>
                                    </template>
                                    
                                    <template x-if="selectedSpan.metadata && selectedSpan.metadata.args">
                                        <div class="mb-2">
                                            <div class="text-gray-500">Arguments:</div>
                                            <pre class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="JSON.stringify(selectedSpan.metadata.args, null, 2)"></pre>
                                        </div>
                                    </template>
                                    
                                    <template x-if="selectedSpan.metadata && selectedSpan.metadata.result">
                                        <div class="mb-2">
                                            <div class="text-gray-500">Result:</div>
                                            <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="selectedSpan.metadata.result"></div>
                                        </div>
                                    </template>
                                    
                                    <template x-if="selectedSpan.metadata && selectedSpan.metadata.tools">
                                        <div class="mb-2">
                                            <div class="text-gray-500">Available Tools:</div>
                                            <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200">
                                                <template x-for="(tool, index) in selectedSpan.metadata.tools" :key="index">
                                                    <div class="mb-1" x-text="tool"></div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                
                                <div x-show="tab === 'system'" x-cloak>
                                    <template x-if="selectedSpan.metadata && selectedSpan.metadata.system_message">
                                        <div>
                                            <div class="mb-3">
                                                <div class="text-gray-500 font-bold">Default SDK Message:</div>
                                                <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="selectedSpan.metadata.system_message.default"></div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="text-gray-500 font-bold">Agent Instructions:</div>
                                                <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="selectedSpan.metadata.system_message.agent_instructions"></div>
                                            </div>
                                            
                                            <div>
                                                <div class="text-gray-500 font-bold">Combined Message:</div>
                                                <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="selectedSpan.metadata.system_message.combined"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                
                                <div x-show="tab === 'raw'" x-cloak>
                                    <pre x-text="JSON.stringify(selectedSpan.metadata, null, 2)"></pre>
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
    // Initialize Alpine.js data
    window.addEventListener('alpine:init', () => {
        const alpineData = Alpine.$data(document.querySelector('.space-y-6'));
        
        // Auto-select the root span
        if (alpineData.hierarchicalTraces.length > 0) {
            alpineData.setSelectedSpan(alpineData.hierarchicalTraces[0].model);
        }
    });
});
</script>
@endsection

@push('styles')
<style>
.timeline-bar {
    height: 8px;
    background-color: #edf2f7;
    border-radius: 4px;
    overflow: hidden;
}

.timeline-progress {
    height: 100%;
    border-radius: 4px;
}

.timeline-bar.agent_execution .timeline-progress {
    background-color: #4299e1;
}

.timeline-bar.llm_step .timeline-progress {
    background-color: #48bb78;
}

.timeline-bar.handoff .timeline-progress {
    background-color: #ed8936;
}

.timeline-bar.tool_call .timeline-progress {
    background-color: #9f7aea;
}
</style>
@endpush 