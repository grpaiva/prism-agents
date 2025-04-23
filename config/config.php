<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Default LLM Provider and Model
    |--------------------------------------------------------------------------
    |
    | This sets the default provider and model to use when none is specified
    | for an agent. Set to null to require explicit specification.
    |
    */
    'default_provider' => env('PRISM_AGENTS_DEFAULT_PROVIDER', 'openai'),
    'default_model' => env('PRISM_AGENTS_DEFAULT_MODEL', 'gpt-4o'),
    
    /*
    |--------------------------------------------------------------------------
    | Tracing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tracing functionality which logs agent executions, tool calls
    | and responses to the database for later visualization.
    |
    */
    'tracing' => [
        'enabled' => env('PRISM_AGENTS_TRACING_ENABLED', true),
        
        // The database connection to use for traces (null = default)
        'connection' => env('PRISM_AGENTS_TRACING_CONNECTION', null),
        
        // The table name for storing traces
        'table' => env('PRISM_AGENTS_TRACING_TABLE', 'prism_agent_traces'),
        
        // Maximum age of traces to keep (in days, 0 to keep forever)
        'retention_days' => env('PRISM_AGENTS_TRACING_RETENTION_DAYS', 30),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Agent Defaults
    |--------------------------------------------------------------------------
    |
    | Default settings for agents
    |
    */
    'agent_defaults' => [
        // Maximum number of tool calls per agent execution (0 for unlimited)
        'max_tool_calls' => env('PRISM_AGENTS_MAX_TOOL_CALLS', 10),
        
        // Maximum depth of handoffs between agents
        'max_handoff_depth' => env('PRISM_AGENTS_MAX_HANDOFF_DEPTH', 5),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Tool Configuration
    |--------------------------------------------------------------------------
    |
    | Configure global settings for tools
    |
    */
    'tools' => [
        // Automatically infer JSON schema for tool parameters
        'infer_parameters' => env('PRISM_AGENTS_INFER_TOOL_PARAMETERS', true),
    ],
];