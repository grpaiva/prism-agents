# PrismAgents - Agent Framework for Laravel & Prism PHP

PrismAgents is a Laravel package that adds OpenAI Agents SDK-like functionality to [Prism PHP](https://github.com/prism-php/prism). It provides a simple yet powerful way to build agent-based AI applications with features like tools, handoffs between agents, guardrails, and tracing.

![GitHub Actions](https://github.com/grpaiva/prism-agents/actions/workflows/main.yml/badge.svg)

## Installation

You can install the package via composer:

```bash
composer require grpaiva/prism-agents
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --provider="Grpaiva\PrismAgents\PrismAgentsServiceProvider"
```

Run the migrations:

```bash
php artisan migrate
```

## Basic Usage

### Creating Agents

```php
use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\PrismAgents;
use Prism\Prism\Enums\Provider;

// Create a basic agent with the builder pattern
$agent = Agent::as('assistant')
    ->withInstructions('You are a helpful assistant that answers user questions.')
    ->using(Provider::OpenAI, 'gpt-4o');

// Run the agent
$result = PrismAgents::run($agent, "What's the weather like in Paris?");

// Get the response
echo $result->getOutput();
```

### Using Tools

```php
use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\PrismAgents;
use Prism\Prism\Tool;
use Prism\Prism\Enums\Provider;

// Create a weather tool using the builder pattern
$weatherTool = Tool::as('get_weather')
    ->for('Get the current weather for a location')
    ->withStringParameter('location', 'The city or location to get weather for')
    ->using(function ($args) {
        $location = $args['location'];
        return "The weather in {$location} is sunny and 72°F.";
    });

// Create an agent with the tool
$agent = Agent::as('weather_assistant')
    ->withInstructions('You are a helpful assistant that can check the weather.')
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withTools([$weatherTool]);

// Run the agent
$result = PrismAgents::run($agent, "What's the weather like in Paris?");

// Get the response
echo $result->getOutput();

// Check if any tools were used
if (count($result->getToolResults()) > 0) {
    foreach ($result->getToolResults() as $toolResult) {
        echo "Tool used: " . $toolResult['toolName'] . "\n";
        echo "Result: " . json_encode($toolResult['result']) . "\n";
    }
}
```

### Creating Tool Classes

For more complex tools, you can create dedicated classes:

```php
use Prism\Prism\Tool;
use Illuminate\Support\Facades\Http;

class WeatherTool extends Tool
{
    public function __construct()
    {
        parent::__construct('weather');
        
        $this
            ->for('Get current weather conditions')
            ->withStringParameter('city', 'The city to get weather for')
            ->using($this);
    }

    public function __invoke($args)
    {
        $city = $args['city'];
        
        // Call weather API
        $response = Http::get('https://weather-api.example.com', [
            'q' => $city,
            'units' => 'metric',
            'appid' => config('services.weather.api_key'),
        ]);

        $data = $response->json();
        
        return "The weather in {$city} is {$data['description']} with a temperature of {$data['temp']}°C.";
    }
}
```

### Agent Handoffs (Agents as Tools)

```php
use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\PrismAgents;
use Prism\Prism\Enums\Provider;

// Create specialized agents
$weatherAgent = Agent::as('weather_agent')
    ->withInstructions('You are a weather specialist. Provide detailed weather information.')
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withHandoffDescription('A specialist in weather information');

// Create a general agent that uses the weather agent as a tool
$generalAgent = Agent::as('general_assistant')
    ->withInstructions('You are a helpful assistant. If the user asks about weather, use the weather_agent tool.')
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withTools([$weatherAgent->asTool()]);

// Run the general agent
$result = PrismAgents::run($generalAgent, "What's the weather forecast for New York?");

// Output the result
echo $result->getOutput();
```

### Multi-Agent Orchestration

```php
use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\PrismAgents;
use Prism\Prism\Enums\Provider;

// Create specialized translation agents
$spanishAgent = Agent::as('spanish_agent')
    ->withInstructions('You translate the user\'s message to Spanish')
    ->using(Provider::OpenAI, 'gpt-4.1-nano')
    ->withHandoffDescription('An english to spanish translator');

$frenchAgent = Agent::as('french_agent')
    ->withInstructions('You translate the user\'s message to French')
    ->using(Provider::OpenAI, 'gpt-4.1-nano')
    ->withHandoffDescription('An english to french translator');

// Create an orchestrator agent
$orchestratorAgent = Agent::as('orchestrator_agent')
    ->withInstructions("You are a translation agent. Use the tools given to you to translate.")
    ->using(Provider::OpenAI, 'gpt-4.1')
    ->withTools([
        $spanishAgent->asTool(),
        $frenchAgent->asTool()
    ]);

// Run the orchestrator with trace by name
$result = PrismAgents::run($orchestratorAgent, "Translate 'Hello, how are you?' to Spanish and French.")
    ->withTrace('translation_process');

// Output the result
echo $result->getOutput();
```

### Tracing

```php
use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\PrismAgents;
use Prism\Prism\Enums\Provider;

// Create and run an agent
$agent = Agent::as('assistant')
    ->withInstructions('You are a helpful assistant.')
    ->using(Provider::OpenAI, 'gpt-4o');

// Method 1: Use withTrace with a string name
$result = PrismAgents::run($agent, "Hello, how are you?")
    ->withTrace('my_trace');

// Method 2: Pass trace name directly to run method
$result = PrismAgents::run($agent, "Hello, how are you?", 'another_trace');

// Method 3: For more control, you can still create a Trace object
$trace = Trace::as('custom_trace');
$trace->withConnection('custom_db_connection');
$result = PrismAgents::run($agent, "Hello, how are you?", $trace);

// Retrieve the trace data (assuming you have the trace name)
$traceData = \Grpaiva\PrismAgents\Trace::retrieve('my_trace');
if ($traceData) {
    echo "Number of spans: " . count($traceData->getSpans());
}
```

#### Tracing Database Storage

The package creates a `prism_agent_traces` table that stores detailed information about agent executions:

- Basic trace information: trace ID, span ID, parent span ID, timestamps
- Agent details: name, provider, model
- Execution metrics: tokens used, steps, tool calls
- Content: input text, output text, status, errors

The tracing system automatically adapts to the available schema, making it compatible with any database supported by Laravel. For optimal performance with high-volume tracing, consider using a dedicated connection for trace data.

### Guardrails

```php
use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\PrismAgents;
use Grpaiva\PrismAgents\Guardrail;
use Grpaiva\PrismAgents\GuardrailResult;
use Grpaiva\PrismAgents\AgentContext;
use Prism\Prism\Enums\Provider;

// Create a custom guardrail
class ProfanityGuardrail extends Guardrail
{
    public function check($input, AgentContext $context): GuardrailResult
    {
        // Simple example - check for a banned word
        if (is_string($input) && stripos($input, 'badword') !== false) {
            return GuardrailResult::fail('Input contains profanity', 400);
        }
        
        return GuardrailResult::pass();
    }
}

// Create an agent with the guardrail
$agent = Agent::as('safe_assistant')
    ->withInstructions('You are a helpful assistant.')
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withInputGuardrails([ProfanityGuardrail::as('profanity_filter')]);

try {
    // This will throw a GuardrailException if the input fails the guardrail
    $result = PrismAgents::run($agent, "Hello, can you help me with badword?");
    echo $result->getOutput();
} catch (\Grpaiva\PrismAgents\Exceptions\GuardrailException $e) {
    echo "Guardrail triggered: " . $e->getMessage();
}
```

## Advanced Configuration

The package comes with sensible defaults, but you can customize its behavior by modifying the configuration file published at `config/prism-agents.php`. Here's a detailed explanation of available configuration options:

### Provider and Model Settings

*Not implemented yet.*
```php
// config/prism-agents.php
return [
    // Default LLM provider and model when none is specified for an agent
    'default_provider' => env('PRISM_AGENTS_DEFAULT_PROVIDER', 'openai'),
    'default_model' => env('PRISM_AGENTS_DEFAULT_MODEL', 'gpt-4o'),
    
    // ... other configuration options ...
];
```

### Tracing Configuration
*All implemented except for `retention_days`*

```php
// config/prism-agents.php
return [
    // ... other options ...
    
    'tracing' => [
        // Enable or disable tracing functionality
        'enabled' => env('PRISM_AGENTS_TRACING_ENABLED', true),
        
        // The database connection to use for traces (null = default connection)
        'connection' => env('PRISM_AGENTS_TRACING_CONNECTION', null),
        
        // The table name for storing traces
        'table' => env('PRISM_AGENTS_TRACING_TABLE', 'prism_agent_traces'),
        
        // Maximum age of traces to keep (in days, 0 to keep forever)
        'retention_days' => env('PRISM_AGENTS_TRACING_RETENTION_DAYS', 30),
    ],
    
    // ... other configuration options ...
];
```

### Agent Default Settings
*Not implemented yet.*

```php
// config/prism-agents.php
return [
    // ... other options ...
    
    'agent_defaults' => [
        // Maximum number of tool calls per agent execution (0 for unlimited)
        'max_tool_calls' => env('PRISM_AGENTS_MAX_TOOL_CALLS', 10),
        
        // Maximum depth of handoffs between agents
        'max_handoff_depth' => env('PRISM_AGENTS_MAX_HANDOFF_DEPTH', 5),
    ],
    
    // ... other configuration options ...
];
```

### UI Configuration
*All implemented.*

```php
// config/prism-agents.php
return [
    // ... other options ...
    
    'ui' => [
        // Whether to enable the web UI for trace visualization
        'enabled' => env('PRISM_AGENTS_UI_ENABLED', true),
        
        // Route path prefix (default: /prism-agents)
        'route_prefix' => env('PRISM_AGENTS_UI_ROUTE_PREFIX', 'prism-agents'),
        
        // Middleware for UI routes
        'middleware' => env('PRISM_AGENTS_UI_MIDDLEWARE', 'web'),
    ],
];
```

### Environment Variables

For convenience, you can configure many options via environment variables:

| Environment Variable | Description | Default | Status |
|-------------|-------------|---------|---------|
| `PRISM_AGENTS_DEFAULT_PROVIDER` | Default LLM provider | `openai` | Coming soon |
| `PRISM_AGENTS_DEFAULT_MODEL` | Default LLM model | `gpt-4o` | Coming soon |
| `PRISM_AGENTS_TRACING_ENABLED` | Enable tracing | `true` | Implemented |
| `PRISM_AGENTS_TRACING_CONNECTION` | Database connection for traces | `null` (default) | Implemented |
| `PRISM_AGENTS_TRACING_TABLE` | Database table for traces | `prism_agent_traces` | Implemented |
| `PRISM_AGENTS_TRACING_RETENTION_DAYS` | Days to retain trace data | `30` | Coming soon |
| `PRISM_AGENTS_MAX_TOOL_CALLS` | Max tool calls per agent execution | `10` | Coming soon |
| `PRISM_AGENTS_MAX_HANDOFF_DEPTH` | Max depth of agent handoffs | `5` | Coming soon |
| `PRISM_AGENTS_UI_ENABLED` | Enable web UI | `true` | Implemented |
| `PRISM_AGENTS_UI_ROUTE_PREFIX` | Route prefix for UI | `prism-agents` | Implemented |
| `PRISM_AGENTS_UI_MIDDLEWARE` | Middleware for UI routes | `web` | Implemented |

## License

The PrismAgents package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).