<?php

namespace Tests\Support;

use App\Mcp\Servers\SpecificationsServer;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tools\ToolSearch;

class DirectSpecificationsServer extends SpecificationsServer
{
    public function createContext(): ServerContext
    {
        $catalog = $this->tools[ToolSearch::class] ?? [];
        unset($this->tools[ToolSearch::class]);
        $this->tools = [...$this->tools, ...$catalog];

        return parent::createContext();
    }
}
