<?php

namespace App\Services\Http;

use App\Services\Scraping\UserAgentRotator;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

final class GuzzleHttpClientFactory implements HttpClientFactory
{
    public function __construct(
        private readonly UserAgentRotator $userAgents,
        private readonly float $timeout = 15.0,
        private readonly float $connectTimeout = 10.0,
    ) {}

    public function make(array $overrides = []): ClientInterface
    {
        $defaults = [
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'http_errors' => true,
            'headers' => [
                'User-Agent' => $this->userAgents->next(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
        ];

        return new Client(array_replace_recursive($defaults, $overrides));
    }
}
