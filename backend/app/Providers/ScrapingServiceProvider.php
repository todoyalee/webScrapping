<?php

namespace App\Providers;

use App\Services\Http\GuzzleHttpClientFactory;
use App\Services\Http\HttpClientFactory;
use App\Services\Proxy\HttpProxyProvider;
use App\Services\Proxy\NullProxyProvider;
use App\Services\Proxy\ProxyProvider;
use App\Services\Scraping\ProductScraper;
use App\Services\Scraping\UserAgentRotator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;

class ScrapingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserAgentRotator::class, fn () => new UserAgentRotator(
            config('scraper.user_agents'),
        ));

        $this->app->bind(HttpClientFactory::class, fn (Application $app) => new GuzzleHttpClientFactory(
            $app->make(UserAgentRotator::class),
            (float) config('scraper.timeout'),
            (float) config('scraper.connect_timeout'),
        ));

        $this->app->bind(ProxyProvider::class, function (Application $app) {
            if (! config('scraper.proxy.enabled')) {
                return new NullProxyProvider;
            }

            return new HttpProxyProvider(
                $app->make(Http::class),
                config('scraper.proxy.base_url'),
                config('scraper.proxy.token'),
                (float) config('scraper.proxy.timeout'),
            );
        });

        $this->app->bind(ProductScraper::class, fn (Application $app) => new ProductScraper(
            $app->make(HttpClientFactory::class),
            $app->make(ProxyProvider::class),
            config('scraper.selectors'),
            (int) config('scraper.retries'),
        ));
    }
}
