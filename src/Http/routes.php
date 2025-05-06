<?php

use Grpaiva\PrismAgents\Http\Controllers\TraceController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

$prefix = Config::get('prism-agents.ui.route_prefix', 'prism-agents');
$middleware = Config::get('prism-agents.ui.middleware', 'web');

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('prism-agents.')
    ->group(function () {
        Route::get('/traces', [TraceController::class, 'index'])->name('traces.index');
        Route::get('/traces/{traceId}', [TraceController::class, 'show'])->name('traces.show');
    });
