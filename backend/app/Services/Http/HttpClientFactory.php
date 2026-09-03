<?php

namespace App\Services\Http;

use GuzzleHttp\ClientInterface;

interface HttpClientFactory
{
    /**
     * Build a fresh HTTP client. Each call rotates the User-Agent; $overrides is
     * merged over the defaults (used to inject a per-request `proxy`).
     *
     * @param  array<string, mixed>  $overrides
     */
    public function make(array $overrides = []): ClientInterface;
}
