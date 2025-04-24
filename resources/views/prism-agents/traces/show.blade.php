@extends('prism-agents::layouts.app')

@section('title', 'Trace Details')

@section('content')
<div class="flex items-center mb-6">
    <a href="{{ route('prism-agents.traces.index') }}" class="text-indigo-600 hover:text-indigo-800 flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Back to Traces
    </a>
    <h1 class="text-2xl font-semibold text-gray-900 ml-2">Trace Details</h1>
    
    <div class="ml-auto">
        <button onclick="window.location.reload()" class="p-2 rounded-md hover:bg-gray-100" title="Refresh">
            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
        </button>
    </div>
</div>

<div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
    <div class="grid grid-cols-4 gap-0">
        <div class="p-4 border-r border-gray-200">
            <div class="text-xs font-medium text-gray-500 uppercase">WORKFLOW</div>
            <div class="mt-1 text-sm font-medium text-gray-900">{{ $trace->workflow_name ?? 'Unknown' }}</div>
        </div>
        <div class="p-4 border-r border-gray-200">
            <div class="text-xs font-medium text-gray-500 uppercase">TRACE ID</div>
            <div class="mt-1 text-sm font-medium text-gray-900 truncate" title="{{ $trace->id }}">{{ $trace->id }}</div>
        </div>
        <div class="p-4 border-r border-gray-200">
            <div class="text-xs font-medium text-gray-500 uppercase">CREATED</div>
            <div class="mt-1 text-sm font-medium text-gray-900">{{ $trace->created_at->format('M j, Y, g:i A') }}</div>
        </div>
        <div class="p-4">
            <div class="text-xs font-medium text-gray-500 uppercase">DURATION</div>
            <div class="mt-1 text-sm font-medium text-gray-900">{{ $trace->formatted_duration ?? 'N/A' }}</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-3 gap-6">
    <!-- Timeline (2/3 width) -->
    <div class="col-span-2">
        <div class="bg-white shadow-md rounded-lg p-4" x-data="{
            selectedSpanId: null,
            expandedItems: {},
            selectSpan(spanId) {
                this.selectedSpanId = spanId;
            }
        }">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Timeline</h3>
            
            <div class="space-y-4">
                @foreach($trace->spans as $span)
                    @php
                        // Parse span_data JSON if it's a string
                        if (is_string($span->span_data)) {
                            $spanData = json_decode($span->span_data, true);
                        } else {
                            $spanData = $span->span_data ?? [];
                        }
                        
                        // Extract type and name from span data
                        $type = $spanData['type'] ?? 'unknown';
                        $name = '';
                        
                        switch ($type) {
                            case 'agent':
                                $name = $spanData['name'] ?? 'Unknown Agent';
                                break;
                            case 'function':
                                $name = $spanData['name'] ?? 'Unknown Function';
                                $toolCallId = $spanData['tool_call_id'] ?? null;
                                $input = $spanData['input'] ?? null;
                                $output = $spanData['output'] ?? null;
                                break;
                            case 'response':
                                $responseId = $spanData['response_id'] ?? null;
                                $text = $spanData['text'] ?? '';
                                $toolCalls = $spanData['tool_calls'] ?? [];
                                break;
                            case 'handoff':
                                $fromAgent = $spanData['from_agent'] ?? 'Unknown';
                                $toAgent = $spanData['to_agent'] ?? 'Unknown';
                                $message = $spanData['message'] ?? null;
                                break;
                        }
                        
                        // Handle negative durations by using absolute value
                        $durationMs = abs($span->duration_ms);
                    @endphp
                    
                    <div class="relative" x-data="{ open: false }">
                        <div class="flex items-center cursor-pointer" @click="open = !open; selectSpan('{{ $span->id }}')">
                            <div class="mr-2 w-5 h-5 flex-shrink-0 rounded-full bg-gray-300 flex items-center justify-center">
                                <div class="w-3 h-3 rounded-full bg-{{ $type === 'agent' ? 'purple' : ($type === 'function' ? 'blue' : ($type === 'handoff' ? 'green' : 'indigo')) }}-500"></div>
                            </div>
                            
                            <div class="mr-2 text-sm text-gray-700">{{ number_format($durationMs) }} ms</div>
                            
                            <div class="flex-grow h-1 bg-gray-200 rounded">
                                <div class="h-1 bg-{{ $type === 'agent' ? 'purple' : ($type === 'function' ? 'blue' : ($type === 'handoff' ? 'green' : 'indigo')) }}-500 rounded" 
                                    style="width: {{ ($trace->duration_ms != 0) ? number_format((abs($durationMs) / abs($trace->duration_ms)) * 100) : 0 }}%;">
                                </div>
                            </div>
                            
                            <div class="ml-2">
                                <svg class="w-5 h-5 text-gray-400 transform transition-transform" 
                                    :class="{'rotate-180': open}" 
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Child Spans (if any) -->
                        <div class="pl-7 mt-2 space-y-2" x-show="open" x-cloak>
                            @if(isset($span->children) && count($span->children) > 0)
                                @foreach($span->children as $childSpan)
                                    @php
                                        // Parse child span data
                                        if (is_string($childSpan->span_data)) {
                                            $childSpanData = json_decode($childSpan->span_data, true);
                                        } else {
                                            $childSpanData = $childSpan->span_data ?? [];
                                        }
                                        
                                        $childType = $childSpanData['type'] ?? 'unknown';
                                        $childDurationMs = abs($childSpan->duration_ms);
                                    @endphp
                                    
                                    <div class="flex items-center cursor-pointer" @click.stop="selectSpan('{{ $childSpan->id }}')">
                                        <div class="mr-2 w-4 h-4 flex-shrink-0 rounded-full bg-gray-300 flex items-center justify-center">
                                            <div class="w-2 h-2 rounded-full bg-{{ $childType === 'agent' ? 'purple' : ($childType === 'function' ? 'blue' : 'gray') }}-500"></div>
                                        </div>
                                        
                                        <div class="mr-2 text-sm text-gray-700">{{ number_format($childDurationMs) }} ms</div>
                                        
                                        <div class="flex-grow h-1 bg-gray-200 rounded">
                                            <div class="h-1 bg-{{ $childType === 'agent' ? 'purple' : ($childType === 'function' ? 'blue' : 'gray') }}-500 rounded" 
                                                style="width: {{ ($trace->duration_ms != 0) ? number_format((abs($childDurationMs) / abs($trace->duration_ms)) * 100) : 0 }}%;">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-sm text-gray-500">No child spans</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    
    <!-- Details Panel (1/3 width) -->
    <div class="col-span-1">
        <div class="bg-white shadow-md rounded-lg p-4" x-data="{
            selectedSpanId: null
        }">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Details</h3>
            
            <template x-if="!selectedSpanId">
                <div class="text-gray-500 text-sm p-4 bg-gray-50 rounded-md">
                    Select a span from the timeline to view its details
                </div>
            </template>
            
            @foreach($trace->spans as $span)
                @php
                    // Parse span_data JSON if it's a string
                    if (is_string($span->span_data)) {
                        $spanData = json_decode($span->span_data, true);
                    } else {
                        $spanData = $span->span_data ?? [];
                    }
                    
                    // Extract type and name from span data
                    $type = $spanData['type'] ?? 'unknown';
                    $durationMs = abs($span->duration_ms);
                @endphp
                
                <div x-show="selectedSpanId === '{{ $span->id }}'" x-cloak>
                    <div class="mb-4">
                        <div class="text-xs font-medium text-gray-500 uppercase mb-1">TYPE</div>
                        <div class="text-sm font-medium text-gray-900">{{ ucfirst($type) }}</div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="text-xs font-medium text-gray-500 uppercase mb-1">DURATION</div>
                        <div class="text-sm font-medium text-gray-900">{{ number_format($durationMs) }} ms</div>
                    </div>
                    
                    @if($type === 'agent')
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">AGENT</div>
                            <div class="text-sm font-medium text-gray-900">{{ $spanData['name'] ?? 'Unknown' }}</div>
                        </div>
                        
                        @if(isset($spanData['output_type']))
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">OUTPUT TYPE</div>
                            <div class="text-sm font-medium text-gray-900">{{ $spanData['output_type'] }}</div>
                        </div>
                        @endif
                        
                        @if(isset($spanData['tools']) && count($spanData['tools']) > 0)
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">AVAILABLE TOOLS</div>
                            <div class="text-sm text-gray-900">
                                @foreach($spanData['tools'] as $tool)
                                    <div class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-700 mr-1 mb-1">
                                        {{ is_array($tool) ? ($tool['name'] ?? 'Unknown') : $tool }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endif
                    
                    @if($type === 'function')
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">FUNCTION</div>
                            <div class="text-sm font-medium text-gray-900">{{ $spanData['name'] ?? 'Unknown' }}</div>
                        </div>
                        
                        @if(isset($spanData['tool_call_id']))
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">TOOL CALL ID</div>
                            <div class="text-sm font-medium text-gray-900 break-all">{{ $spanData['tool_call_id'] }}</div>
                        </div>
                        @endif
                        
                        @if(isset($spanData['input']))
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">INPUT</div>
                            <div class="text-sm text-gray-900 bg-gray-50 p-2 rounded border border-gray-200 max-h-64 overflow-y-auto">
                                <pre class="whitespace-pre-wrap">{{ is_array($spanData['input']) ? json_encode($spanData['input'], JSON_PRETTY_PRINT) : $spanData['input'] }}</pre>
                            </div>
                        </div>
                        @endif
                        
                        @if(isset($spanData['output']))
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">OUTPUT</div>
                            <div class="text-sm text-gray-900 bg-gray-50 p-2 rounded border border-gray-200 max-h-64 overflow-y-auto">
                                <pre class="whitespace-pre-wrap">{{ is_array($spanData['output']) ? json_encode($spanData['output'], JSON_PRETTY_PRINT) : $spanData['output'] }}</pre>
                            </div>
                        </div>
                        @endif
                    @endif
                    
                    @if($type === 'response')
                        @if(isset($spanData['response_id']))
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">RESPONSE ID</div>
                            <div class="text-sm font-medium text-gray-900 break-all">{{ $spanData['response_id'] }}</div>
                        </div>
                        @endif
                        
                        @if(isset($spanData['text']) && !empty($spanData['text']))
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">TEXT</div>
                            <div class="text-sm text-gray-900 bg-gray-50 p-2 rounded border border-gray-200 max-h-64 overflow-y-auto">
                                <pre class="whitespace-pre-wrap">{{ $spanData['text'] }}</pre>
                            </div>
                        </div>
                        @endif
                        
                        @if(isset($spanData['tool_calls']) && count($spanData['tool_calls']) > 0)
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">TOOL CALLS</div>
                            <div class="space-y-2">
                                @foreach($spanData['tool_calls'] as $toolCall)
                                    <div class="text-sm text-gray-900 bg-gray-50 p-2 rounded border border-gray-200">
                                        <div class="font-medium">{{ $toolCall['name'] ?? 'Unknown Tool' }}</div>
                                        <div class="text-xs text-gray-500 break-all">ID: {{ $toolCall['id'] ?? 'Unknown' }}</div>
                                        @if(isset($toolCall['args']) && !empty($toolCall['args']))
                                            <div class="mt-1 text-xs">
                                                <div class="font-medium text-gray-500">Arguments:</div>
                                                <pre class="whitespace-pre-wrap text-gray-900">{{ is_array($toolCall['args']) ? json_encode($toolCall['args'], JSON_PRETTY_PRINT) : $toolCall['args'] }}</pre>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endif
                    
                    @if($type === 'handoff')
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">FROM</div>
                            <div class="text-sm font-medium text-gray-900">{{ $spanData['from_agent'] ?? 'Unknown' }}</div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">TO</div>
                            <div class="text-sm font-medium text-gray-900">{{ $spanData['to_agent'] ?? 'Unknown' }}</div>
                        </div>
                        
                        @if(isset($spanData['message']))
                        <div class="mb-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">MESSAGE</div>
                            <div class="text-sm text-gray-900 bg-gray-50 p-2 rounded border border-gray-200 max-h-64 overflow-y-auto">
                                <pre class="whitespace-pre-wrap">{{ $spanData['message'] }}</pre>
                            </div>
                        </div>
                        @endif
                    @endif
                    
                    @if(isset($spanData['error']) && $spanData['error'])
                        <div class="mb-4">
                            <div class="text-xs font-medium text-red-500 uppercase mb-1">ERROR</div>
                            <div class="text-sm text-red-600 bg-red-50 p-2 rounded border border-red-100 max-h-64 overflow-y-auto">
                                <pre class="whitespace-pre-wrap">{{ is_array($spanData['error']) ? json_encode($spanData['error'], JSON_PRETTY_PRINT) : $spanData['error'] }}</pre>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection 