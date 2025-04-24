@extends('prism-agents::layouts.app')

@section('title', 'Trace Details')

@section('content')
<div class="mb-4">
    <a href="{{ route('prism-agents.traces.index') }}" class="text-indigo-600 hover:text-indigo-900">← Back to Traces</a>
</div>

<div class="mb-4 bg-white shadow sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <div class="flex justify-between">
            <div>
                <h2 class="text-lg font-medium text-gray-900">
                    {{ $trace->workflow_name ?? 'Trace' }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">{{ $trace->id }}</p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Created: {{ $trace->created_at->format('M j, Y g:i A') }}</p>
                <p class="text-sm text-gray-500">Duration: {{ $trace->formatted_duration }}</p>
            </div>
        </div>
    </div>
</div>

<div class="flex gap-4">
    <!-- Timeline -->
    <div class="flex-grow bg-white shadow sm:rounded-lg" x-data="{
        expandedTraces: {},
        selectedSpan: null,
        
        toggleExpand(traceId) {
            this.expandedTraces[traceId] = !this.expandedTraces[traceId];
        },
        
        selectSpan(span) {
            this.selectedSpan = span;
        },
        
        typeToIcon(type) {
            const icons = {
                'agent': '🤖',
                'handoff': '🔄',
                'function': '🛠️',
                'response': '💬',
                'llm_step': '💭',
                'tool_call': '🧰'
            };
            return icons[type] || '📄';
        },
        
        getPercentage(duration) {
            return Math.min(100, Math.max(0.5, (duration / {{ $totalDuration }}) * 100));
        }
    }">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Timeline</h3>
            <p class="mt-1 text-sm text-gray-500">Execution spans and their relationships</p>
        </div>
        
        <div class="overflow-auto" style="max-height: 70vh;">
            <div class="divide-y divide-gray-200">
                <template x-for="traceItem in {{ json_encode($hierarchicalSpans) }}" :key="traceItem.model.id">
                    <div 
                        :class="{ 
                            'hidden': !traceItem.visible,
                            'bg-indigo-50': selectedSpan && selectedSpan.id === traceItem.model.id
                        }"
                        class="hover:bg-gray-50 cursor-pointer"
                        @click="selectSpan(traceItem.model)"
                    >
                        <div class="px-4 py-2">
                            <div class="flex items-center">
                                <!-- Indentation based on level -->
                                <div :style="{ width: (traceItem.level * 20) + 'px' }" class="flex-shrink-0"></div>
                                
                                <!-- Expand/collapse icon if has children -->
                                <div class="w-5 flex-shrink-0" @click.stop="toggleExpand(traceItem.model.id)">
                                    <template x-if="traceItem.children.length > 0">
                                        <svg :class="{ 'transform rotate-90': expandedTraces[traceItem.model.id] }" class="h-4 w-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    </template>
                                </div>
                                
                                <!-- Type icon -->
                                <div class="w-7 text-center">
                                    <span x-text="typeToIcon(traceItem.model.span_data?.type || '')"></span>
                                </div>
                                
                                <!-- Name and duration -->
                                <div class="ml-2 flex-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <template x-if="traceItem.model.span_data?.type === 'agent'">
                                                <span class="text-sm font-medium text-gray-900" x-text="traceItem.model.span_data?.name"></span>
                                            </template>
                                            
                                            <template x-if="traceItem.model.span_data?.type === 'handoff'">
                                                <span class="text-sm font-medium text-gray-900">
                                                    <span x-text="traceItem.model.span_data?.from_agent"></span>
                                                    <span class="text-gray-500 mx-1">→</span>
                                                    <span x-text="traceItem.model.span_data?.to_agent"></span>
                                                </span>
                                            </template>
                                            
                                            <template x-if="traceItem.model.span_data?.type === 'function'">
                                                <span class="text-sm font-medium text-gray-900" x-text="traceItem.model.span_data?.name || 'Function'"></span>
                                            </template>
                                            
                                            <template x-if="traceItem.model.span_data?.type === 'response'">
                                                <span class="text-sm font-medium text-gray-900">Response</span>
                                            </template>
                                            
                                            <template x-if="!['agent', 'handoff', 'function', 'response'].includes(traceItem.model.span_data?.type)">
                                                <span class="text-sm font-medium text-gray-900" x-text="traceItem.model.span_data?.name || traceItem.model.span_data?.type || 'Unknown'"></span>
                                            </template>
                                        </div>
                                        <div class="text-xs text-gray-500" x-text="traceItem.model.duration_ms + ' ms'"></div>
                                    </div>
                                    <div class="mt-1 timeline-bar" :class="traceItem.model.span_data?.type">
                                        <div 
                                            class="timeline-progress" 
                                            :style="{ width: getPercentage(traceItem.model.duration_ms) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
    
    <!-- Span details -->
    <div class="w-1/3 bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Details</h3>
            <p class="mt-1 text-sm text-gray-500">Select a span to view details</p>
        </div>
        
        <div class="p-4 overflow-auto" style="max-height: 70vh;">
            <template x-if="!selectedSpan">
                <div class="text-center py-6 text-gray-500">
                    Select a span from the timeline to view its details
                </div>
            </template>
            
            <template x-if="selectedSpan">
                <div>
                    <!-- Basic information -->
                    <div class="mb-4">
                        <h4 class="text-base font-medium text-gray-900 mb-2">Overview</h4>
                        <div class="bg-gray-50 p-3 rounded border border-gray-200 text-sm">
                            <div class="mb-2">
                                <span class="text-gray-500">ID:</span>
                                <span class="text-gray-900" x-text="selectedSpan.id"></span>
                            </div>
                            
                            <div class="mb-2">
                                <span class="text-gray-500">Type:</span>
                                <span class="text-gray-900" x-text="selectedSpan.span_data?.type"></span>
                            </div>
                            
                            <div class="mb-2">
                                <span class="text-gray-500">Started:</span>
                                <span class="text-gray-900" x-text="new Date(selectedSpan.started_at).toLocaleString()"></span>
                            </div>
                            
                            <div class="mb-2">
                                <span class="text-gray-500">Duration:</span>
                                <span class="text-gray-900" x-text="selectedSpan.duration_ms + ' ms'"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Span content based on type -->
                    <template x-if="selectedSpan.span_data">
                        <div class="mb-4">
                            <h4 class="text-base font-medium text-gray-900 mb-2">Content</h4>
                            <div class="bg-gray-50 p-3 rounded border border-gray-200 text-sm">
                                <!-- Agent -->
                                <template x-if="selectedSpan.span_data.type === 'agent'">
                                    <div>
                                        <div class="mb-2">
                                            <span class="text-gray-500">Agent Name:</span>
                                            <span class="text-gray-900" x-text="selectedSpan.span_data.name"></span>
                                        </div>
                                        
                                        <template x-if="selectedSpan.span_data.tools && selectedSpan.span_data.tools.length > 0">
                                            <div class="mb-2">
                                                <div class="text-gray-500">Tools:</div>
                                                <div class="mt-1">
                                                    <template x-for="tool in selectedSpan.span_data.tools" :key="tool">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-1 mb-1">
                                                            <span x-text="tool"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        
                                        <template x-if="selectedSpan.span_data.handoffs && selectedSpan.span_data.handoffs.length > 0">
                                            <div class="mb-2">
                                                <div class="text-gray-500">Handoffs:</div>
                                                <div class="mt-1">
                                                    <template x-for="handoff in selectedSpan.span_data.handoffs" :key="handoff">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-1 mb-1">
                                                            <span x-text="handoff"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                
                                <!-- Function -->
                                <template x-if="selectedSpan.span_data.type === 'function'">
                                    <div>
                                        <div class="mb-2">
                                            <span class="text-gray-500">Function Name:</span>
                                            <span class="text-gray-900" x-text="selectedSpan.span_data.name"></span>
                                        </div>
                                        
                                        <template x-if="selectedSpan.span_data.input">
                                            <div class="mb-2">
                                                <div class="text-gray-500">Input:</div>
                                                <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="typeof selectedSpan.span_data.input === 'string' ? selectedSpan.span_data.input : JSON.stringify(selectedSpan.span_data.input, null, 2)"></div>
                                            </div>
                                        </template>
                                        
                                        <template x-if="selectedSpan.span_data.output">
                                            <div class="mb-2">
                                                <div class="text-gray-500">Output:</div>
                                                <div class="text-gray-900 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-gray-200" x-text="selectedSpan.span_data.output"></div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                
                                <!-- Handoff -->
                                <template x-if="selectedSpan.span_data.type === 'handoff'">
                                    <div>
                                        <div class="mb-2">
                                            <span class="text-gray-500">From Agent:</span>
                                            <span class="text-gray-900" x-text="selectedSpan.span_data.from_agent"></span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <span class="text-gray-500">To Agent:</span>
                                            <span class="text-gray-900" x-text="selectedSpan.span_data.to_agent"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Error information if present -->
                    <template x-if="selectedSpan.error">
                        <div class="mb-4">
                            <h4 class="text-base font-medium text-red-600 mb-2">Error</h4>
                            <div class="bg-red-50 p-3 rounded border border-red-200 text-sm">
                                <div class="mb-2">
                                    <span class="text-red-600" x-text="selectedSpan.error.message"></span>
                                </div>
                                
                                <template x-if="selectedSpan.error.data">
                                    <div class="text-gray-800 whitespace-pre-wrap mt-1 pl-2 border-l-2 border-red-300" x-text="JSON.stringify(selectedSpan.error.data, null, 2)"></div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<style>
    .timeline-bar {
        height: 4px;
        background-color: #e5e7eb;
        border-radius: 2px;
        overflow: hidden;
    }
    .timeline-progress {
        height: 100%;
        background-color: #6366f1;
        border-radius: 2px;
    }
    
    .timeline-bar.agent .timeline-progress {
        background-color: #6366f1; /* indigo-500 */
    }
    
    .timeline-bar.handoff .timeline-progress {
        background-color: #84cc16; /* lime-500 */
    }
    
    .timeline-bar.function .timeline-progress {
        background-color: #f97316; /* orange-500 */
    }
    
    .timeline-bar.response .timeline-progress {
        background-color: #14b8a6; /* teal-500 */
    }
</style>
@endsection 