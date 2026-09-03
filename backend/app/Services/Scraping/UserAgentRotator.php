<?php

namespace App\Services\Scraping;

use InvalidArgumentException;

/**
 * Cycles through a fixed list of User-Agent strings so consecutive requests do
 * not share the same client fingerprint.
 */
final class UserAgentRotator
{
    private int $cursor = 0;

    /** @var list<string> */
    private array $agents;

    /**
     * @param  list<string>  $agents
     */
    public function __construct(array $agents)
    {
        $agents = array_values(array_filter($agents));

        if ($agents === []) {
            throw new InvalidArgumentException('At least one User-Agent is required.');
        }

        $this->agents = $agents;
    }

    /**
     * Return the next User-Agent in round-robin order.
     */
    public function next(): string
    {
        $agent = $this->agents[$this->cursor];
        $this->cursor = ($this->cursor + 1) % count($this->agents);

        return $agent;
    }
}
