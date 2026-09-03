<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default target
    |--------------------------------------------------------------------------
    |
    | The product page scraped when no URL is passed to `scrape:product` or the
    | POST /api/products/scrape endpoint. It defaults to books.toscrape.com, a
    | sandbox built for scraping practice, so the demo works out of the box.
    | Point SCRAPER_TARGET_URL at a real store (e.g. Jumia) in production.
    |
    */

    'target_url' => env('SCRAPER_TARGET_URL', 'https://books.toscrape.com/catalogue/a-light-in-the-attic_1000/index.html'),

    /*
    |--------------------------------------------------------------------------
    | CSS selectors
    |--------------------------------------------------------------------------
    |
    | How to pull each field out of the fetched HTML. Override per target site
    | via env without touching code. `price` is normalised to a float by
    | App\Services\Scraping\PriceParser, so a raw "£51.77" is fine here.
    |
    */

    'selectors' => [
        'title' => env('SCRAPER_SELECTOR_TITLE', '.product_main h1'),
        'price' => env('SCRAPER_SELECTOR_PRICE', '.product_main .price_color'),
        'image' => env('SCRAPER_SELECTOR_IMAGE', '#product_gallery img'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP behaviour
    |--------------------------------------------------------------------------
    */

    'timeout' => (float) env('SCRAPER_TIMEOUT', 15),
    'connect_timeout' => (float) env('SCRAPER_CONNECT_TIMEOUT', 10),
    'retries' => (int) env('SCRAPER_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | User-Agent rotation
    |--------------------------------------------------------------------------
    |
    | One of these is picked per request to vary the client fingerprint. This
    | mimics proxy rotation at the header level; real IP rotation is handled by
    | the Go proxy-service below.
    |
    */

    'user_agents' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64; rv:126.0) Gecko/20100101 Firefox/126.0',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36',
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxy service (Go microservice)
    |--------------------------------------------------------------------------
    |
    | When enabled, the scraper asks the Go proxy-service for an upstream proxy
    | before each request and reports the outcome back so unhealthy proxies are
    | rotated out. When disabled it falls back to a direct connection.
    |
    */

    'proxy' => [
        'enabled' => env('PROXY_SERVICE_ENABLED', false),
        'base_url' => env('PROXY_SERVICE_URL', 'http://localhost:9000'),
        'token' => env('PROXY_SERVICE_TOKEN'),
        'timeout' => (float) env('PROXY_SERVICE_TIMEOUT', 3),
    ],

];
