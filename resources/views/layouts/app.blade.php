<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Prism Agents') }} - @yield('title', 'Trace Viewer')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <!-- Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.12.0/dist/cdn.min.js" defer></script>
    
    <style>
        [x-cloak] { display: none !important; }
        
        .timeline-bar {
            height: 8px;
            border-radius: 4px;
            background-color: #e5e7eb;
            overflow: hidden;
        }
        
        .timeline-progress {
            height: 100%;
            background: linear-gradient(90deg, #60a5fa 0%, #3b82f6 100%);
            border-radius: 4px;
        }
        
        .timeline-bar.tool {
            background-color: #e5e7eb;
        }
        
        .timeline-bar.handoff {
            background-color: #fef3c7;
        }
        
        .timeline-bar.handoff .timeline-progress {
            background: linear-gradient(90deg, #fbbf24 0%, #f59e0b 100%);
        }
        
        .timeline-bar.llm_step {
            background-color: #e0f2fe;
        }
        
        .timeline-bar.llm_step .timeline-progress {
            background: linear-gradient(90deg, #38bdf8 0%, #0ea5e9 100%);
        }
        
        .timeline-bar.agent_execution {
            background-color: #dcfce7;
        }
        
        .timeline-bar.agent_execution .timeline-progress {
            background: linear-gradient(90deg, #4ade80 0%, #22c55e 100%);
        }
        
        .timeline-bar.agent_run {
            background-color: #d1fae5;
        }
        
        .timeline-bar.agent_run .timeline-progress {
            background: linear-gradient(90deg, #34d399 0%, #10b981 100%);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <h1 class="text-xl font-bold text-gray-900">Prism Agents</h1>
                        </div>
                        <nav class="ml-6 flex space-x-8">
                            <a href="{{ route('prism-agents.traces.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('prism-agents.traces.*') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }} text-sm font-medium">
                                Traces
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                @yield('content')
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="py-4 text-center text-sm text-gray-500">
                    Prism Agents &copy; {{ date('Y') }}
                </div>
            </div>
        </footer>
    </div>

    @yield('scripts')
</body>
</html> 