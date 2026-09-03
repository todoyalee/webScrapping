<?php

namespace App\Services\Scraping;

use App\Services\Http\HttpClientFactory;
use App\Services\Proxy\ProxyProvider;
use App\Services\Scraping\Exceptions\ScrapeException;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

/**
 * Fetches a single eCommerce product page and extracts its title, price and
 * image. Each request goes out through a rotated User-Agent and, when enabled,
 * an upstream proxy borrowed from the Go proxy-service. Failed proxies are
 * reported back and the request is retried.
 */
final class ProductScraper
{
    /**
     * @param  array{title: string, price: string, image: string}  $selectors
     */
    public function __construct(
        private readonly HttpClientFactory $clients,
        private readonly ProxyProvider $proxies,
        private readonly array $selectors,
        private readonly int $retries = 2,
    ) {}

    public function scrape(string $url): ScrapedProduct
    {
        $crawler = new Crawler($this->fetch($url), $url);

        $title = $this->firstText($crawler, $this->selectors['title']);
        if ($title === null || $title === '') {
            throw ScrapeException::titleNotFound($url, $this->selectors['title']);
        }

        return new ScrapedProduct(
            title: $title,
            price: PriceParser::parse($this->firstText($crawler, $this->selectors['price'])),
            imageUrl: $this->firstImage($crawler, $this->selectors['image'], $url),
            sourceUrl: $url,
        );
    }

    private function fetch(string $url): string
    {
        $lastError = 'unknown error';

        for ($attempt = 0; $attempt <= $this->retries; $attempt++) {
            $proxy = $this->proxies->next();
            $options = $proxy !== null ? ['proxy' => $proxy] : [];

            try {
                $body = (string) $this->clients->make($options)
                    ->request('GET', $url)
                    ->getBody();

                if ($proxy !== null) {
                    $this->proxies->report($proxy, true);
                }

                return $body;
            } catch (GuzzleException $e) {
                $lastError = $e->getMessage();

                if ($proxy !== null) {
                    $this->proxies->report($proxy, false);
                }
            }
        }

        throw ScrapeException::requestFailed($url, $lastError);
    }

    private function firstText(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector);

        return $node->count() > 0 ? trim($node->first()->text()) : null;
    }

    private function firstImage(Crawler $crawler, string $selector, string $baseUrl): ?string
    {
        $node = $crawler->filter($selector);
        if ($node->count() === 0) {
            return null;
        }

        $ref = $node->first()->attr('src')
            ?? $node->first()->attr('data-src')
            ?? $node->first()->attr('data-lazy-src');

        return $ref !== null ? UriResolver::resolve($ref, $baseUrl) : null;
    }
}
