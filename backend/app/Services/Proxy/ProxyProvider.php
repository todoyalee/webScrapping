<?php

namespace App\Services\Proxy;

interface ProxyProvider
{
    /**
     * Return the next upstream proxy URL to use, or null for a direct connection.
     */
    public function next(): ?string;

    /**
     * Report whether a proxy handed out by next() worked, so the provider can
     * rotate unhealthy proxies out.
     */
    public function report(string $proxy, bool $ok): void;
}
