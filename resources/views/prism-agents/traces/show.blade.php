@extends('prism-agents::layouts.app')

@section('title', 'Trace Details')

@section('content')
<div x-data="{
    tab: 'timeline',
    detailsTab: 'basic',
    selectedSpanId: null,
    selectedSpan: null,
    hierarchicalTraces: {{ json_encode($hierarchicalTraces) }},
    totalDuration: {{ $totalDuration }},
    formatDuration(ms) {
        return Number(Math.abs(ms)).toFixed(2) + ' ms';
    },
    formatTimestamp(timestamp) {
        return new Date(timestamp).toLocaleString();
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
                return '→';
            case 'tool_call':
                return '🔧';
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
            case 'llm_step':
                return 'bg-green-100 text-green-800';
            case 'handoff':
                return 'bg-orange-100 text-orange-800';
            case 'tool_call':
                return 'bg-purple-100 text-purple-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    },
    getStatusColor(status) {
        switch(status) {
            case 'success':
                return 'bg-green-100 text-green-800';
            case 'error':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    }
}"
class="space-y-6">

    <!-- Header with trace overview -->
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-6 py-4 sm:flex sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center">
                    <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                        <a href="{{ route('prism-agents.traces.index') }}" class="text-indigo-600 hover:text-indigo-900 mr-2">
                            Traces
                        </a>
                        <svg class="h-4 w-4 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="truncate max-w-md">{{ $rootSpan->name }}</span>
                    </h2>
                    <span class="ml-2 px-2.5 py-0.5 inline-flex text-xs leading-5 font-medium rounded-full" 
                          :class="getBadgeColorByType('{{ $rootSpan->type }}')">
                        {{ $rootSpan->type }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 flex items-center">
                    <span class="font-mono">{{ $traceId }}</span>
                    <span class="mx-2">•</span> 
                    <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-medium rounded-full" 
                          :class="getStatusColor('{{ $rootSpan->status_value }}')">
                        {{ ucfirst($rootSpan->status_value) }}
                    </span>
                </p>
            </div>
            <div class="mt-4 sm:mt-0 flex flex-col items-end text-sm">
                <div class="flex items-center space-x-4">
                    @if($rootSpan->agent_name)
                    <div>
                        <span class="text-gray-500">Agent:</span>
                        <span class="font-medium">{{ $rootSpan->agent_name }}</span>
                    </div>
                    @endif
                    
                    @if($rootSpan->model)
                    <div>
                        <span class="text-gray-500">Model:</span>
                        <span class="font-medium">{{ $rootSpan->model }}</span>
                    </div>
                    @endif
                </div>
                <div class="flex items-center space-x-4 mt-1">
                    <div>
                        <span class="text-gray-500">Started:</span>
                        <span class="font-medium">{{ $rootSpan->started_at->format('M j, Y g:i:s A') }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Duration:</span>
                        <span class="font-medium">{{ $rootSpan->formatted_duration }}</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabs for trace exploration -->
        <div class="border-t border-gray-200">
            <div class="px-6 py-3">
                <div class="flex space-x-4">
                    <button 
                        @click="tab = 'timeline'" 
                        :class="{ 'text-indigo-600 border-indigo-600': tab === 'timeline', 'text-gray-500 border-transparent': tab !== 'timeline' }"
                        class="px-3 py-2 border-b-2 font-medium text-sm">
                        Timeline
                    </button>
                    <button 
                        @click="tab = 'steps'" 
                        :class="{ 'text-indigo-600 border-indigo-600': tab === 'steps', 'text-gray-500 border-transparent': tab !== 'steps' }"
                        class="px-3 py-2 border-b-2 font-medium text-sm">
                        Steps
                    </button>
                    <button 
                        @click="tab = 'tools'" 
                        :class="{ 'text-indigo-600 border-indigo-600': tab === 'tools', 'text-gray-500 border-transparent': tab !== 'tools' }"
                        class="px-3 py-2 border-b-2 font-medium text-sm">
                        Tool Calls
                    </button>
                    <button 
                        @click="tab = 'info'" 
                        :class="{ 'text-indigo-600 border-indigo-600': tab === 'info', 'text-gray-500 border-transparent': tab !== 'info' }"
                        class="px-3 py-2 border-b-2 font-medium text-sm">
                        Trace Info
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content area with tabs -->
    <div x-show="tab === 'timeline'" class="flex space-x-4">
        <!-- Trace timeline -->
        <div class="w-2/3 bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Execution Timeline</h3>
                <p class="mt-1 text-sm text-gray-500">Trace duration: <span x-text="formatDuration(totalDuration)"></span></p>
            </div>
            
            <div class="divide-y divide-gray-200 max-h-[60vh] overflow-y-auto">
                <template x-for="(traceItem, index) in hierarchicalTraces" :key="traceItem.model.id">
                    <div 
                        class="px-4 py-3 sm:px-6 cursor-pointer hover:bg-gray-50 transition duration-150"
                        :class="{ 
                            'bg-blue-50': selectedSpanId === traceItem.model.id
                        }"
                        @click.stop="setSelectedSpan(traceItem.model)"
                    >
                        <div class="flex items-center">
                            <!-- Indentation based on level -->
                            <div :style="{ width: (traceItem.level * 20) + 'px' }" class="flex-shrink-0"></div>
                            
                            <!-- Type icon -->
                            <div class="w-7 text-center flex-shrink-0">
                                <span 
                                    class="inline-flex items-center justify-center w-6 h-6 rounded-full" 
                                    :class="getBadgeColorByType(traceItem.model.type)"
                                    x-text="typeToIcon(traceItem.model.type)"></span>
                            </div>
                            
                            <div class="flex-1 pl-3">
                                <div class="flex justify-between">
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-900" x-text="isHandoff(traceItem.model) ? getHandoffName(traceItem.model) : getStepName(traceItem.model)"></span>
                                        
                                        <!-- Type badge -->
                                        <span 
                                            class="ml-2 px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full"
                                            :class="getBadgeColorByType(traceItem.model.type)"
                                            x-text="traceItem.model.type">
                                        </span>
                                        
                                        <!-- Handoff indicator for steps with handoffs -->
                                        <template x-if="traceItem.model.type === 'llm_step' && getHandoffCount(traceItem.model) > 0">
                                            <span class="ml-2 px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
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
        
        <!-- Span details panel -->
        <div class="w-1/3 bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Details</h3>
                <p class="mt-1 text-sm text-gray-500">Select a span to view details</p>
            </div>
            
            <div x-cloak x-show="selectedSpan" class="divide-y divide-gray-200">
                <!-- Span header information -->
                <div class="px-4 py-3 sm:px-6 bg-gray-50">
                    <div class="flex justify-between">
                        <div>
                            <span 
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                :class="getBadgeColorByType(selectedSpan?.type)"
                                x-text="selectedSpan?.type">
                            </span>
                            <h3 class="text-base font-medium mt-1" x-text="selectedSpan?.name"></h3>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500">Duration</p>
                            <p class="text-sm font-medium" x-text="formatDuration(selectedSpan?.duration)"></p>
                        </div>
                    </div>
                </div>
                
                <!-- Detail tabs navigation -->
                <div class="px-4 py-3 sm:px-6 bg-white">
                    <div class="flex space-x-4">
                        <button 
                            @click="detailsTab = 'basic'" 
                            :class="{ 'text-indigo-600 border-indigo-600': detailsTab === 'basic', 'text-gray-500 border-transparent': detailsTab !== 'basic' }"
                            class="pb-2 border-b-2 text-sm font-medium">
                            Basic
                        </button>
                        <button 
                            @click="detailsTab = 'metadata'" 
                            :class="{ 'text-indigo-600 border-indigo-600': detailsTab === 'metadata', 'text-gray-500 border-transparent': detailsTab !== 'metadata' }"
                            class="pb-2 border-b-2 text-sm font-medium"
                            x-show="selectedSpan?.metadata">
                            Metadata
                        </button>
                        <button 
                            @click="detailsTab = 'system'" 
                            :class="{ 'text-indigo-600 border-indigo-600': detailsTab === 'system', 'text-gray-500 border-transparent': detailsTab !== 'system' }"
                            class="pb-2 border-b-2 text-sm font-medium"
                            x-show="selectedSpan?.metadata && selectedSpan?.metadata.system_message">
                            System
                        </button>
                        <button 
                            @click="detailsTab = 'raw'" 
                            :class="{ 'text-indigo-600 border-indigo-600': detailsTab === 'raw', 'text-gray-500 border-transparent': detailsTab !== 'raw' }"
                            class="pb-2 border-b-2 text-sm font-medium">
                            Raw
                        </button>
                    </div>
                </div>
                
                <!-- Detail content area -->
                <div class="overflow-auto max-h-[50vh]">
                    <!-- Basic info tab -->
                    <div x-show="detailsTab === 'basic'" class="px-4 py-3 sm:px-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Span ID</h4>
                                <p class="mt-1 text-sm text-gray-900 break-all font-mono" x-text="selectedSpan?.id"></p>
                            </div>
                            
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Parent ID</h4>
                                <p class="mt-1 text-sm text-gray-900 break-all font-mono" x-text="selectedSpan?.parent_id || 'None'"></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Started At</h4>
                                <p class="mt-1 text-sm text-gray-900" x-text="formatTimestamp(selectedSpan?.started_at)"></p>
                            </div>
                            
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Ended At</h4>
                                <p class="mt-1 text-sm text-gray-900" x-text="selectedSpan?.ended_at ? formatTimestamp(selectedSpan?.ended_at) : 'N/A'"></p>
                            </div>
                        </div>
                        
                        <template x-if="selectedSpan?.agent_name">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Agent</h4>
                                <p class="mt-1 text-sm text-gray-900" x-text="selectedSpan?.agent_name"></p>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.model">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Model</h4>
                                <p class="mt-1 text-sm text-gray-900" x-text="selectedSpan?.model"></p>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.provider">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Provider</h4>
                                <p class="mt-1 text-sm text-gray-900" x-text="selectedSpan?.provider"></p>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.status">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Status</h4>
                                <p class="mt-1">
                                    <span 
                                        class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-medium rounded-full"
                                        :class="getStatusColor(selectedSpan?.status)"
                                        x-text="selectedSpan?.status">
                                    </span>
                                </p>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.tokens_used">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Tokens Used</h4>
                                <p class="mt-1 text-sm text-gray-900" x-text="selectedSpan?.tokens_used"></p>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.input_text">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Input</h4>
                                <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-32 overflow-y-auto" x-text="selectedSpan?.input_text"></div>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.output_text">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Output</h4>
                                <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-32 overflow-y-auto" x-text="selectedSpan?.output_text"></div>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.error_message">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Error</h4>
                                <div class="mt-1 text-sm text-red-600 bg-red-50 p-3 rounded border border-red-200 whitespace-pre-wrap max-h-32 overflow-y-auto" x-text="selectedSpan?.error_message"></div>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Metadata tab -->
                    <div x-show="detailsTab === 'metadata'" class="px-4 py-3 sm:px-6 space-y-4">
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.agent">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Agent</h4>
                                <p class="mt-1 text-sm text-gray-900" x-text="selectedSpan?.metadata.agent"></p>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.provider">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Provider</h4>
                                <p class="mt-1 text-sm text-gray-900" x-text="selectedSpan?.metadata.provider"></p>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.model">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Model</h4>
                                <p class="mt-1 text-sm text-gray-900" x-text="selectedSpan?.metadata.model"></p>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.input">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Input</h4>
                                <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-48 overflow-y-auto" x-text="selectedSpan?.metadata.input"></div>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.output">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Output</h4>
                                <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-48 overflow-y-auto" x-text="selectedSpan?.metadata.output"></div>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.text">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Text</h4>
                                <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-48 overflow-y-auto" x-text="selectedSpan?.metadata.text"></div>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.step_index !== undefined">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Step Index</h4>
                                <p class="mt-1 text-sm text-gray-900" x-text="selectedSpan?.metadata.step_index"></p>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.finish_reason">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Finish Reason</h4>
                                <p class="mt-1 text-sm text-gray-900" x-text="selectedSpan?.metadata.finish_reason"></p>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.status">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Status</h4>
                                <p class="mt-1">
                                    <span 
                                        class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-medium rounded-full"
                                        :class="getStatusColor(selectedSpan?.metadata.status)"
                                        x-text="selectedSpan?.metadata.status">
                                    </span>
                                </p>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.error">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Error</h4>
                                <div class="mt-1 text-sm text-red-600 bg-red-50 p-3 rounded border border-red-200 whitespace-pre-wrap max-h-48 overflow-y-auto" x-text="selectedSpan?.metadata.error"></div>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.tool_name">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Tool</h4>
                                <p class="mt-1 text-sm text-gray-900" x-text="selectedSpan?.metadata.tool_name"></p>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.args">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Arguments</h4>
                                <pre class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-48 overflow-y-auto font-mono text-xs" x-text="JSON.stringify(selectedSpan?.metadata.args, null, 2)"></pre>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.result">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Result</h4>
                                <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-48 overflow-y-auto" x-text="selectedSpan?.metadata.result"></div>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.tools && selectedSpan?.metadata.tools.length > 0">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Available Tools</h4>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <template x-for="(tool, index) in selectedSpan?.metadata.tools" :key="index">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800" x-text="tool"></span>
                                    </template>
                                </div>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.metadata && selectedSpan?.metadata.metadata.usage">
                            <div>
                                <h4 class="text-xs font-medium text-gray-500">Token Usage</h4>
                                <div class="mt-1 grid grid-cols-3 gap-2 text-center">
                                    <div class="bg-blue-50 p-2 rounded">
                                        <span class="text-xs text-gray-500">Prompt</span>
                                        <p class="font-medium text-sm" x-text="selectedSpan?.metadata.metadata.usage.prompt_tokens"></p>
                                    </div>
                                    <div class="bg-green-50 p-2 rounded">
                                        <span class="text-xs text-gray-500">Completion</span>
                                        <p class="font-medium text-sm" x-text="selectedSpan?.metadata.metadata.usage.completion_tokens"></p>
                                    </div>
                                    <div class="bg-purple-50 p-2 rounded">
                                        <span class="text-xs text-gray-500">Total</span>
                                        <p class="font-medium text-sm" x-text="selectedSpan?.metadata.metadata.usage.total_tokens"></p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    
                    <!-- System message tab -->
                    <div x-show="detailsTab === 'system'" class="px-4 py-3 sm:px-6 space-y-4">
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.system_message">
                            <div class="space-y-4">
                                <div>
                                    <h4 class="text-xs font-medium text-gray-500">Default SDK Message</h4>
                                    <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-60 overflow-y-auto" x-text="selectedSpan?.metadata.system_message.default"></div>
                                </div>
                                
                                <div>
                                    <h4 class="text-xs font-medium text-gray-500">Agent Instructions</h4>
                                    <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-60 overflow-y-auto" x-text="selectedSpan?.metadata.system_message.agent_instructions"></div>
                                </div>
                                
                                <div>
                                    <h4 class="text-xs font-medium text-gray-500">Combined Message</h4>
                                    <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-60 overflow-y-auto" x-text="selectedSpan?.metadata.system_message.combined"></div>
                                </div>
                            </div>
                        </template>
                        
                        <template x-if="selectedSpan?.metadata && selectedSpan?.metadata.metadata && selectedSpan?.metadata.metadata.system_message">
                            <div class="space-y-4">
                                <div>
                                    <h4 class="text-xs font-medium text-gray-500">Default SDK Message</h4>
                                    <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-60 overflow-y-auto" x-text="selectedSpan?.metadata.metadata.system_message.default"></div>
                                </div>
                                
                                <div>
                                    <h4 class="text-xs font-medium text-gray-500">Agent Instructions</h4>
                                    <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-60 overflow-y-auto" x-text="selectedSpan?.metadata.metadata.system_message.agent_instructions"></div>
                                </div>
                                
                                <div>
                                    <h4 class="text-xs font-medium text-gray-500">Combined Message</h4>
                                    <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap max-h-60 overflow-y-auto" x-text="selectedSpan?.metadata.metadata.system_message.combined"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Raw JSON tab -->
                    <div x-show="detailsTab === 'raw'" class="px-4 py-3 sm:px-6">
                        <pre class="text-xs font-mono bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap overflow-auto max-h-[50vh]" x-text="JSON.stringify(selectedSpan, null, 2)"></pre>
                    </div>
                </div>
            </div>
            
            <div class="px-4 py-5 sm:px-6" x-cloak x-show="!selectedSpan">
                <p class="text-sm text-gray-500">Click on a span in the timeline to view its details</p>
            </div>
        </div>
    </div>

    <!-- Steps tab view -->
    <div x-show="tab === 'steps'" class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Agent Steps</h3>
            <p class="mt-1 text-sm text-gray-500">Detailed view of agent reasoning steps</p>
        </div>
        
        <div class="max-h-[70vh] overflow-y-auto">
            <template x-for="(traceItem, index) in hierarchicalTraces.filter(t => t.model.type === 'llm_step')" :key="traceItem.model.id">
                <div class="border-b border-gray-200 px-4 py-5 sm:px-6 hover:bg-gray-50 transition-colors duration-150">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <span class="w-7 h-7 flex items-center justify-center bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                <span x-text="traceItem.model.metadata && traceItem.model.metadata.step_index !== undefined ? traceItem.model.metadata.step_index : '?'"></span>
                            </span>
                            <h4 class="ml-2 text-sm font-medium text-gray-900" x-text="getStepName(traceItem.model)"></h4>
                            <template x-if="traceItem.model.metadata && traceItem.model.metadata.finish_reason">
                                <span class="ml-2 px-2.5 py-0.5 inline-flex text-xs leading-5 font-medium rounded-full bg-gray-100 text-gray-800" x-text="traceItem.model.metadata.finish_reason"></span>
                            </template>
                        </div>
                        <div class="text-sm text-gray-500" x-text="getDisplayTime(traceItem.model)"></div>
                    </div>
                    
                    <!-- Step content -->
                    <div>
                        <template x-if="traceItem.model.metadata && traceItem.model.metadata.text && traceItem.model.metadata.text.trim() !== ''">
                            <div class="mt-2">
                                <div class="mb-1 text-xs font-medium text-gray-500">Output:</div>
                                <div class="bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap text-sm" x-text="traceItem.model.metadata.text"></div>
                            </div>
                        </template>
                        
                        <!-- If step has tool calls -->
                        <template x-if="traceItem.model.metadata && traceItem.model.metadata.finish_reason === 'toolcalls' && traceItem.model.metadata.tools && traceItem.model.metadata.tools.length > 0">
                            <div class="mt-3">
                                <div class="flex items-center mb-2">
                                    <div class="text-xs font-medium text-gray-500">Used tools:</div>
                                    <div class="ml-2 flex flex-wrap gap-1">
                                        <template x-for="(tool, idx) in traceItem.model.metadata.tools" :key="idx">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800" x-text="tool"></span>
                                        </template>
                                    </div>
                                </div>
                                
                                <!-- Find and show child handoffs for this step -->
                                <template x-for="handoff in hierarchicalTraces.filter(t => t.model.parent_id === traceItem.model.id && t.model.type === 'handoff')">
                                    <div class="ml-4 mt-2 border-l-2 border-orange-200 pl-3">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <span class="text-sm font-medium text-gray-900" x-text="getHandoffName(handoff.model)"></span>
                                                <span class="ml-2 px-2 py-0.5 inline-flex text-xs leading-5 font-medium rounded-full bg-orange-100 text-orange-800">handoff</span>
                                            </div>
                                            <div class="text-xs text-gray-500" x-text="getDisplayTime(handoff.model)"></div>
                                        </div>
                                        
                                        <template x-if="handoff.model.metadata && handoff.model.metadata.args">
                                            <div class="mt-1">
                                                <div class="text-xs text-gray-500">Arguments:</div>
                                                <pre class="mt-1 text-xs font-mono bg-gray-50 p-2 rounded border border-gray-200" x-text="JSON.stringify(handoff.model.metadata.args, null, 2)"></pre>
                                            </div>
                                        </template>
                                        
                                        <template x-if="handoff.model.metadata && handoff.model.metadata.result">
                                            <div class="mt-2">
                                                <div class="text-xs text-gray-500">Result:</div>
                                                <div class="mt-1 text-sm bg-gray-50 p-2 rounded border border-gray-200 whitespace-pre-wrap" x-text="handoff.model.metadata.result"></div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
            
            <template x-if="hierarchicalTraces.filter(t => t.model.type === 'llm_step').length === 0">
                <div class="px-4 py-5 sm:px-6 text-center text-sm text-gray-500">
                    No steps found in this trace.
                </div>
            </template>
        </div>
    </div>
    
    <!-- Tool Calls tab view -->
    <div x-show="tab === 'tools'" class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Tool Calls & Agent Handoffs</h3>
            <p class="mt-1 text-sm text-gray-500">All tool calls and agent handoffs in this trace</p>
        </div>
        
        <div class="max-h-[70vh] overflow-y-auto">
            <template x-for="(traceItem, index) in hierarchicalTraces.filter(t => t.model.type === 'handoff')" :key="traceItem.model.id">
                <div class="border-b border-gray-200 px-4 py-5 sm:px-6 hover:bg-gray-50 transition-colors duration-150">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center">
                            <span class="w-7 h-7 flex items-center justify-center bg-orange-100 text-orange-800 rounded-full">
                                →
                            </span>
                            <h4 class="ml-2 text-sm font-medium text-gray-900" x-text="getHandoffName(traceItem.model)"></h4>
                            <span class="ml-2 px-2 py-0.5 inline-flex text-xs leading-5 font-medium rounded-full bg-orange-100 text-orange-800">handoff</span>
                        </div>
                        <div class="text-sm text-gray-500" x-text="getDisplayTime(traceItem.model)"></div>
                    </div>
                    
                    <!-- Parent info -->
                    <template x-if="traceItem.model.parent_id">
                        <div class="mb-3">
                            <div class="text-xs text-gray-500">Called from:</div>
                            <div class="text-sm text-gray-900">
                                <template x-for="parent in hierarchicalTraces.filter(t => t.model.id === traceItem.model.parent_id)">
                                    <span x-text="parent.model.name + ' (' + parent.model.type + ')'"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Handoff arguments -->
                    <template x-if="traceItem.model.metadata && traceItem.model.metadata.args">
                        <div class="mb-3">
                            <div class="text-xs font-medium text-gray-500">Arguments:</div>
                            <pre class="mt-1 text-xs font-mono bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap" x-text="JSON.stringify(traceItem.model.metadata.args, null, 2)"></pre>
                        </div>
                    </template>
                    
                    <!-- Result -->
                    <template x-if="traceItem.model.metadata && traceItem.model.metadata.result">
                        <div>
                            <div class="text-xs font-medium text-gray-500">Result:</div>
                            <div class="mt-1 text-sm bg-gray-50 p-3 rounded border border-gray-200 whitespace-pre-wrap" x-text="traceItem.model.metadata.result"></div>
                        </div>
                    </template>
                </div>
            </template>
            
            <template x-if="hierarchicalTraces.filter(t => t.model.type === 'handoff').length === 0">
                <div class="px-4 py-5 sm:px-6 text-center text-sm text-gray-500">
                    No tool calls or handoffs found in this trace.
                </div>
            </template>
        </div>
    </div>
    
    <!-- Trace Info tab view -->
    <div x-show="tab === 'info'" class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Trace Information</h3>
            <p class="mt-1 text-sm text-gray-500">Complete details about this trace execution</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
            <!-- Basic trace information -->
            <div class="space-y-4">
                <div>
                    <h4 class="text-sm font-medium text-gray-900">General Info</h4>
                    <div class="mt-2 bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-3">
                        <div class="grid grid-cols-3">
                            <div class="text-xs font-medium text-gray-500">Trace ID</div>
                            <div class="col-span-2 text-sm text-gray-900 font-mono break-all">{{ $traceId }}</div>
                        </div>
                        
                        <div class="grid grid-cols-3">
                            <div class="text-xs font-medium text-gray-500">Span ID</div>
                            <div class="col-span-2 text-sm text-gray-900 font-mono break-all">{{ $rootSpan->id }}</div>
                        </div>
                        
                        <div class="grid grid-cols-3">
                            <div class="text-xs font-medium text-gray-500">Status</div>
                            <div class="col-span-2">
                                <span 
                                    class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-medium rounded-full"
                                    :class="getStatusColor('{{ $rootSpan->status_value }}')"
                                >
                                    {{ ucfirst($rootSpan->status_value) }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-3">
                            <div class="text-xs font-medium text-gray-500">Started At</div>
                            <div class="col-span-2 text-sm text-gray-900">{{ $rootSpan->started_at->format('M j, Y g:i:s A') }}</div>
                        </div>
                        
                        <div class="grid grid-cols-3">
                            <div class="text-xs font-medium text-gray-500">Ended At</div>
                            <div class="col-span-2 text-sm text-gray-900">{{ $rootSpan->ended_at ? $rootSpan->ended_at->format('M j, Y g:i:s A') : 'N/A' }}</div>
                        </div>
                        
                        <div class="grid grid-cols-3">
                            <div class="text-xs font-medium text-gray-500">Duration</div>
                            <div class="col-span-2 text-sm text-gray-900">{{ $rootSpan->formatted_duration }}</div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h4 class="text-sm font-medium text-gray-900">Agent Information</h4>
                    <div class="mt-2 bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-3">
                        <div class="grid grid-cols-3">
                            <div class="text-xs font-medium text-gray-500">Agent Name</div>
                            <div class="col-span-2 text-sm text-gray-900">{{ $rootSpan->agent_name ?? 'N/A' }}</div>
                        </div>
                        
                        <div class="grid grid-cols-3">
                            <div class="text-xs font-medium text-gray-500">Model</div>
                            <div class="col-span-2 text-sm text-gray-900">{{ $rootSpan->model ?? 'N/A' }}</div>
                        </div>
                        
                        <div class="grid grid-cols-3">
                            <div class="text-xs font-medium text-gray-500">Provider</div>
                            <div class="col-span-2 text-sm text-gray-900">{{ $rootSpan->provider ?? 'N/A' }}</div>
                        </div>
                        
                        @if($rootSpan->tokens_used)
                        <div class="grid grid-cols-3">
                            <div class="text-xs font-medium text-gray-500">Tokens Used</div>
                            <div class="col-span-2 text-sm text-gray-900">{{ $rootSpan->tokens_used }}</div>
                        </div>
                        @endif
                        
                        <div class="grid grid-cols-3">
                            <div class="text-xs font-medium text-gray-500">Steps</div>
                            <div class="col-span-2 text-sm text-gray-900">{{ $rootSpan->step_count ?? count($hierarchicalTraces->filter(function($t) { return $t['model']['type'] === 'llm_step'; })) }}</div>
                        </div>
                        
                        <div class="grid grid-cols-3">
                            <div class="text-xs font-medium text-gray-500">Tool Calls</div>
                            <div class="col-span-2 text-sm text-gray-900">{{ $rootSpan->tool_call_count ?? count($hierarchicalTraces->filter(function($t) { return $t['model']['type'] === 'handoff'; })) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Input/Output -->
            <div class="space-y-4">
                @if($rootSpan->input_text)
                <div>
                    <h4 class="text-sm font-medium text-gray-900">Input Text</h4>
                    <div class="mt-2 bg-gray-50 p-4 rounded-lg border border-gray-200 whitespace-pre-wrap text-sm">{{ $rootSpan->input_text }}</div>
                </div>
                @endif
                
                @if($rootSpan->output_text)
                <div>
                    <h4 class="text-sm font-medium text-gray-900">Output Text</h4>
                    <div class="mt-2 bg-gray-50 p-4 rounded-lg border border-gray-200 whitespace-pre-wrap text-sm">{{ $rootSpan->output_text }}</div>
                </div>
                @endif
                
                @if($rootSpan->error_message)
                <div>
                    <h4 class="text-sm font-medium text-gray-900">Error Message</h4>
                    <div class="mt-2 bg-red-50 p-4 rounded-lg border border-red-200 whitespace-pre-wrap text-sm text-red-600">{{ $rootSpan->error_message }}</div>
                </div>
                @endif
                
                <!-- Token Usage -->
                @if($rootSpan->metadata && isset($rootSpan->metadata['metadata']) && isset($rootSpan->metadata['metadata']['usage']))
                <div>
                    <h4 class="text-sm font-medium text-gray-900">Token Usage</h4>
                    <div class="mt-2 grid grid-cols-3 gap-3">
                        <div class="bg-blue-50 p-3 rounded-lg border border-blue-200 text-center">
                            <div class="text-xs text-gray-500">Prompt</div>
                            <div class="text-lg font-semibold text-blue-700">{{ $rootSpan->metadata['metadata']['usage']['prompt_tokens'] }}</div>
                        </div>
                        <div class="bg-green-50 p-3 rounded-lg border border-green-200 text-center">
                            <div class="text-xs text-gray-500">Completion</div>
                            <div class="text-lg font-semibold text-green-700">{{ $rootSpan->metadata['metadata']['usage']['completion_tokens'] }}</div>
                        </div>
                        <div class="bg-purple-50 p-3 rounded-lg border border-purple-200 text-center">
                            <div class="text-xs text-gray-500">Total</div>
                            <div class="text-lg font-semibold text-purple-700">{{ $rootSpan->metadata['metadata']['usage']['total_tokens'] }}</div>
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- System Message -->
                @if($rootSpan->metadata && isset($rootSpan->metadata['system_message']))
                <div x-data="{ systemTab: 'default' }">
                    <h4 class="text-sm font-medium text-gray-900">System Message</h4>
                    <div class="mt-2">
                        <div class="flex items-center space-x-2 mb-2">
                            <button 
                                @click="systemTab = 'default'" 
                                :class="{ 'bg-indigo-100 text-indigo-700': systemTab === 'default', 'bg-gray-100 text-gray-700': systemTab !== 'default' }"
                                class="px-3 py-1 rounded text-xs font-medium">
                                System Message
                            </button>
                            <button 
                                @click="systemTab = 'agent'" 
                                :class="{ 'bg-indigo-100 text-indigo-700': systemTab === 'agent', 'bg-gray-100 text-gray-700': systemTab !== 'agent' }"
                                class="px-3 py-1 rounded text-xs font-medium">
                                Agent Instructions
                            </button>
                            <button 
                                @click="systemTab = 'combined'" 
                                :class="{ 'bg-indigo-100 text-indigo-700': systemTab === 'combined', 'bg-gray-100 text-gray-700': systemTab !== 'combined' }"
                                class="px-3 py-1 rounded text-xs font-medium">
                                Combined
                            </button>
                        </div>
                        <div x-show="systemTab === 'default'" class="bg-gray-50 p-4 rounded-lg border border-gray-200 whitespace-pre-wrap text-sm max-h-[20vh] overflow-y-auto">{{ trim($rootSpan->metadata['system_message']['default']) }}</div>
                        
                        <div x-show="systemTab === 'agent'" class="bg-gray-50 p-4 rounded-lg border border-gray-200 whitespace-pre-wrap text-sm max-h-[20vh] overflow-y-auto">{{ trim($rootSpan->metadata['system_message']['agent_instructions']) }}</div>
                        
                        <div x-show="systemTab === 'combined'" class="bg-gray-50 p-4 rounded-lg border border-gray-200 whitespace-pre-wrap text-sm max-h-[20vh] overflow-y-auto">{{ trim($rootSpan->metadata['system_message']['combined']) }}</div>
                    </div>
                </div>
                @endif
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
    position: relative;
}

.timeline-progress {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.timeline-bar.agent_execution .timeline-progress {
    background-color: #3b82f6;
}

.timeline-bar.agent_run .timeline-progress {
    background-color: #6366f1;
}

.timeline-bar.llm_step .timeline-progress {
    background-color: #10b981;
}

.timeline-bar.handoff .timeline-progress {
    background-color: #f97316;
}

.timeline-bar.tool_call .timeline-progress {
    background-color: #8b5cf6;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

[x-cloak] { 
    display: none !important; 
}

/* Tab transitions */
[x-cloak] { display: none; }
[x-show] { animation: fadeIn 0.2s ease-out; }
</style>
@endpush 