<?php

namespace App\Services\Proxy;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Talks to the Go proxy-service over HTTP:
 *
 *   GET  /proxy            -> { "proxy": "http://host:port" }
 *   POST /proxies/report   -> { "proxy": "...", "ok": true|false }
 *
 * Any transport error degrades gracefully to a direct connection so a proxy
 * outage never takes the scraper down.
 */
final class HttpProxyProvider implements ProxyProvider
{
    public function __construct(
        private readonly Http $http,
        private readonly string $baseUrl,
        private readonly ?string $token = null,
        private readonly float $timeout = 3.0,
    ) {}

    public function next(): ?string
    {
        try {
            $response = $this->client()->get('/proxy');

            if ($response->failed()) {
                return null;
            }

            $proxy = $response->json('proxy');

            return is_string($proxy) && $proxy !== '' ? $proxy : null;
        } catch (Throwable $e) {
            Log::warning('proxy-service unreachable, falling back to direct connection', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function report(string $proxy, bool $ok): void
    {
        try {
            $this->client()->post('/proxies/report', ['proxy' => $proxy, 'ok' => $ok]);
        } catch (Throwable $e) {
            Log::debug('failed to report proxy outcome', ['error' => $e->getMessage()]);
        }
    }

    private function client(): PendingRequest
    {
        $request = $this->http->baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeout)
            ->acceptJson();

        return $this->token ? $request->withToken($this->token) : $request;
    }
}
