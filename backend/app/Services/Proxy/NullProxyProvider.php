<?php

namespace App\Services\Proxy;

/**
 * Used when proxy rotation is disabled: every request goes out directly.
 */
final class NullProxyProvider implements ProxyProvider
{
    public function next(): ?string
    {
        return null;
    }

    public function report(string $proxy, bool $ok): void
    {
        // no-op
    }
}
