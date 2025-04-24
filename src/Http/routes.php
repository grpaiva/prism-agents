<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use Grpaiva\PrismAgents\Http\Controllers\TraceController;

$prefix = Config::get('prism-agents.ui.route_prefix', 'prism-agents');
$middleware = Config::get('prism-agents.ui.middleware', 'web');

// Web UI routes
Route::prefix($prefix)
    ->middleware($middleware)
    ->name('prism-agents.')
    ->group(function () {
        Route::get('/traces', [TraceController::class, 'index'])->name('traces.index');
        Route::get('/traces/{traceId}', [TraceController::class, 'show'])->name('traces.show');
    });

// API routes similar to OpenAI's structure
Route::prefix($prefix . '/api/v1')
    ->middleware(['api'])
    ->name('prism-agents.api.')
    ->group(function () {
        Route::get('/traces/{traceId}', [TraceController::class, 'apiGetTrace'])->name('traces.get');
        Route::get('/traces/{traceId}/spans', [TraceController::class, 'apiGetSpans'])->name('traces.spans');
    }); 