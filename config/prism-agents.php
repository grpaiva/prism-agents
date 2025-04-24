'tracing' => [
    'enabled' => env('PRISM_AGENTS_TRACING_ENABLED', true),
    
    // The database connection to use for traces (null = default)
    'connection' => env('PRISM_AGENTS_TRACING_CONNECTION', null),
    
    // Table names for the new schema
    'executions_table' => env('PRISM_AGENTS_EXECUTIONS_TABLE', 'prism_agent_executions'),
    'spans_table' => env('PRISM_AGENTS_SPANS_TABLE', 'prism_agent_spans'),
    
    // Legacy table name (for backwards compatibility)
    'table' => env('PRISM_AGENTS_TRACING_TABLE', 'prism_agent_traces'),
    
    // Maximum age of traces to keep (in days, 0 to keep forever)
    'retention_days' => env('PRISM_AGENTS_TRACING_RETENTION_DAYS', 30),
], 