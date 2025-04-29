@extends('prism-agents::layouts.app')

@section('title', 'Traces')

@section('content')
<div class="bg-white shadow sm:rounded-lg">
    <div class="px-6 py-4 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Agent Traces</h2>
            <p class="mt-1 text-sm text-gray-500">View execution traces of your agents and their tools.</p>
        </div>
        
        <div class="flex items-center space-x-3">
            <form method="get" action="{{ route('prism-agents.traces.index') }}" class="flex items-center space-x-2">
                <label for="per_page" class="text-sm text-gray-700">Traces per page:</label>
                <select 
                    id="per_page" 
                    name="per_page" 
                    class="block pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                    onchange="this.form.submit()"
                >
                    <option value="" disabled>Per page</option>
                    @foreach([10, 25, 50, 100, 250, 500] as $option)
                        <option value="{{ $option }}" {{ isset($perPage) && $perPage == $option ? 'selected' : '' }}>{{ $option }} rows</option>
                    @endforeach
                </select>
            </form>
                
            <form method="get" action="{{ route('prism-agents.traces.index') }}" class="flex items-center space-x-2">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <label for="sort" class="text-sm text-gray-700">Sort by:</label>
                <select 
                    id="sort" 
                    name="sort" 
                    class="block pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                    onchange="this.form.submit()"
                >
                    <option value="started_at" {{ $sortField === 'started_at' ? 'selected' : '' }}>Started At</option>
                    <option value="created_at" {{ $sortField === 'created_at' ? 'selected' : '' }}>Created At</option>
                    <option value="name" {{ $sortField === 'name' ? 'selected' : '' }}>Name</option>
                    <option value="type" {{ $sortField === 'type' ? 'selected' : '' }}>Type</option>
                    <option value="duration" {{ $sortField === 'duration' ? 'selected' : '' }}>Duration</option>
                    <option value="status" {{ $sortField === 'status' ? 'selected' : '' }}>Status</option>
                </select>
                
                <select 
                    id="direction" 
                    name="direction" 
                    class="block pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                    onchange="this.form.submit()"
                >
                    <option value="asc" {{ $sortDirection === 'asc' ? 'selected' : '' }}>Ascending</option>
                    <option value="desc" {{ $sortDirection === 'desc' ? 'selected' : '' }}>Descending</option>
                </select>
            </form>
        </div>
    </div>
</div>

<div class="bg-white shadow sm:rounded-lg">
    <div class="overflow-hidden border-b border-gray-200 sm:rounded-lg" x-data="sortable">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('prism-agents.traces.index', [
                            'sort' => 'name',
                            'direction' => ($sortField === 'name' && $sortDirection === 'asc') ? 'desc' : 'asc',
                            'per_page' => $perPage
                        ]) }}" class="group inline-flex items-center">
                            Agent/Name
                            @if($sortField === 'name')
                                <span class="ml-1 text-gray-400">
                                    @if($sortDirection === 'asc')
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 15.586l3.293-3.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </span>
                            @else
                                <span class="ml-1 text-gray-200 group-hover:text-gray-300">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            @endif
                        </a>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('prism-agents.traces.index', [
                            'sort' => 'type',
                            'direction' => ($sortField === 'type' && $sortDirection === 'asc') ? 'desc' : 'asc',
                            'per_page' => $perPage
                        ]) }}" class="group inline-flex items-center">
                            Type
                            @if($sortField === 'type')
                                <span class="ml-1 text-gray-400">
                                    @if($sortDirection === 'asc')
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 15.586l3.293-3.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </span>
                            @else
                                <span class="ml-1 text-gray-200 group-hover:text-gray-300">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            @endif
                        </a>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('prism-agents.traces.index', [
                            'sort' => 'started_at',
                            'direction' => ($sortField === 'started_at' && $sortDirection === 'asc') ? 'desc' : 'asc',
                            'per_page' => $perPage
                        ]) }}" class="group inline-flex items-center">
                            Started
                            @if($sortField === 'started_at')
                                <span class="ml-1 text-gray-400">
                                    @if($sortDirection === 'asc')
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 15.586l3.293-3.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </span>
                            @else
                                <span class="ml-1 text-gray-200 group-hover:text-gray-300">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            @endif
                        </a>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('prism-agents.traces.index', [
                            'sort' => 'duration',
                            'direction' => ($sortField === 'duration' && $sortDirection === 'asc') ? 'desc' : 'asc',
                            'per_page' => $perPage
                        ]) }}" class="group inline-flex items-center">
                            Duration
                            @if($sortField === 'duration')
                                <span class="ml-1 text-gray-400">
                                    @if($sortDirection === 'asc')
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 15.586l3.293-3.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </span>
                            @else
                                <span class="ml-1 text-gray-200 group-hover:text-gray-300">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            @endif
                        </a>
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Handoffs/Tools
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ route('prism-agents.traces.index', [
                            'sort' => 'status',
                            'direction' => ($sortField === 'status' && $sortDirection === 'asc') ? 'desc' : 'asc',
                            'per_page' => $perPage
                        ]) }}" class="group inline-flex items-center">
                            Status
                            @if($sortField === 'status')
                                <span class="ml-1 text-gray-400">
                                    @if($sortDirection === 'asc')
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 15.586l3.293-3.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </span>
                            @else
                                <span class="ml-1 text-gray-200 group-hover:text-gray-300">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            @endif
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($traces as $trace)
                    <tr class="hover:bg-gray-50 transition duration-150">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-full 
                                    @if($trace->type === 'agent_execution') bg-blue-100 text-blue-800 
                                    @elseif($trace->type === 'agent_run') bg-indigo-100 text-indigo-800 
                                    @else bg-gray-100 text-gray-800 @endif">
                                    <span class="text-lg">
                                        @if($trace->type === 'agent_execution') 🤖
                                        @elseif($trace->type === 'agent_run') 🧠
                                        @else 📋 @endif
                                    </span>
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
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-medium rounded-full
                                @if($trace->type === 'agent_execution') bg-blue-100 text-blue-800 
                                @elseif($trace->type === 'agent_run') bg-indigo-100 text-indigo-800 
                                @else bg-gray-100 text-gray-800 @endif">
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
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-medium rounded-full
                                @if($status === 'success') bg-green-100 text-green-800 
                                @elseif($status === 'error') bg-red-100 text-red-800 
                                @else bg-gray-100 text-gray-800 @endif">
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
        
        <div class="px-6 py-4 bg-white border-t border-gray-200 flex items-center justify-between">
            <div class="text-sm text-gray-500">
                Showing <span class="font-medium">{{ $traces->firstItem() ?: 0 }}</span> to <span class="font-medium">{{ $traces->lastItem() ?: 0 }}</span> of <span class="font-medium">{{ $traces->total() }}</span> traces
            </div>
            
            <div class="flex items-center space-x-8">
                <span class="text-sm text-gray-500">Page {{ $traces->currentPage() }} of {{ $traces->lastPage() }}</span>
                <div class="pagination-links">
                    {{ $traces->links() }}
                </div>
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

/* Sortable columns styling */
th a {
    color: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

th a:hover {
    color: #4f46e5;
}

/* Custom pagination styling */
.pagination-links nav {
    display: flex;
    align-items: center;
}

.pagination-links .flex.justify-between {
    display: none; /* Hide the text part of the pagination */
}

.pagination-links .relative.inline-flex {
    position: relative;
    display: inline-flex;
}

.pagination-links span.relative.inline-flex {
    border-radius: 0.375rem;
    margin: 0 0.25rem;
    overflow: hidden;
}

.pagination-links a.relative.inline-flex {
    padding: 0.5rem 0.75rem;
    background-color: white;
    color: #4f46e5;
    font-size: 0.875rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    margin: 0 0.125rem;
    transition: all 0.15s ease-in-out;
}

.pagination-links a.relative.inline-flex:hover {
    background-color: #f3f4f6;
    color: #4338ca;
}

.pagination-links span[aria-current="page"] a {
    background-color: #4f46e5;
    color: white;
    border-color: #4f46e5;
}

.pagination-links span.text-gray-500 {
    margin: 0 0.5rem;
}
</style>
@endpush

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('sortable', () => ({
            init() {
                this.updateSortIndicators();
            },
            updateSortIndicators() {
                const currentSort = '{{ request()->query('sort', 'started_at') }}';
                const currentDirection = '{{ request()->query('direction', 'desc') }}';
                
                // Get all sortable columns
                const sortableColumns = document.querySelectorAll('[data-sort]');
                
                sortableColumns.forEach(column => {
                    const sortField = column.getAttribute('data-sort');
                    const sortDirectionIndicator = column.querySelector('.sort-direction');
                    
                    if (sortField === currentSort) {
                        column.classList.add('text-indigo-600');
                        if (sortDirectionIndicator) {
                            sortDirectionIndicator.classList.remove('hidden');
                            
                            // Show the correct arrow based on direction
                            const ascending = sortDirectionIndicator.querySelector('.ascending');
                            const descending = sortDirectionIndicator.querySelector('.descending');
                            
                            if (currentDirection === 'asc') {
                                ascending.classList.remove('hidden');
                                descending.classList.add('hidden');
                            } else {
                                ascending.classList.add('hidden');
                                descending.classList.remove('hidden');
                            }
                        }
                    }
                });
            }
        }));
    });
</script> 