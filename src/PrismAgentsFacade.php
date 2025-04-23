<?php

namespace Grpaiva\PrismAgents;

use Illuminate\Support\Facades\Facade;

class PrismAgentsFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'prism-agents';
    }
}
