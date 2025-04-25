<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default provider that will be used by PrismAgents
    | when no provider is explicitly specified.
    |
    */
    'default_provider' => env('PRISM_AGENTS_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | This option controls the default model that will be used by PrismAgents
    | when no model is explicitly specified.
    |
    */
    'default_model' => env('PRISM_AGENTS_DEFAULT_MODEL', 'gpt-4o'),
    
    /*
    |--------------------------------------------------------------------------
    | Tracing Configuration
    |--------------------------------------------------------------------------
    |
    | These options control the tracing behavior of PrismAgents.
    |
    */
    'tracing' => [
        'enabled' => env('PRISM_AGENTS_TRACING_ENABLED', true),
        'connection' => env('PRISM_AGENTS_TRACING_CONNECTION', null),
        'retention_days' => env('PRISM_AGENTS_TRACING_RETENTION_DAYS', 30),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | These options control the UI behavior of PrismAgents.
    |
    */
    'ui' => [
        'enabled' => env('PRISM_AGENTS_UI_ENABLED', true),
        'route_prefix' => env('PRISM_AGENTS_UI_ROUTE_PREFIX', 'prism-agents'),
        'middleware' => ['web'],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Agent Defaults
    |--------------------------------------------------------------------------
    |
    | These options control the default behavior of agents.
    |
    */
    'agent' => [
        'max_tool_calls' => env('PRISM_AGENTS_MAX_TOOL_CALLS', 10),
        'max_handoff_depth' => env('PRISM_AGENTS_MAX_HANDOFF_DEPTH', 5),
        'default_temperature' => env('PRISM_AGENTS_DEFAULT_TEMPERATURE', 0.7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Parameter Inference
    |--------------------------------------------------------------------------
    |
    | These options control how tool parameters are inferred.
    |
    */
    'tool_parameter_inference' => [
        'enabled' => env('PRISM_AGENTS_TOOL_PARAMETER_INFERENCE_ENABLED', true),
        'max_depth' => env('PRISM_AGENTS_TOOL_PARAMETER_INFERENCE_MAX_DEPTH', 3),
    ],
];