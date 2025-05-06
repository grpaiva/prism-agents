<?php

namespace Grpaiva\PrismAgents\Facades;

use Grpaiva\PrismAgents\Agent;
use Grpaiva\PrismAgents\AgentContext;
use Grpaiva\PrismAgents\AgentResult;
use Grpaiva\PrismAgents\Trace;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Agent agent(string $name, string $instructions, array $config = [])
 * @method static AgentResult run(Agent $agent, string|array $input, ?AgentContext $context = null)
 * @method static Trace trace(?string $traceId = null)
 * @method static AgentContext context(array $data = [], ?AgentContext $parent = null)
 * @method static \Prism\Prism\Enums\Provider mapProvider(string $provider)
 *
 * @see \Grpaiva\PrismAgents\PrismAgents
 */
class PrismAgents extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'prism-agents';
    }
}
