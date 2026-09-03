# backend — Laravel scraping API

## Prerequisites

- PHP 8.4 with `pdo_mysql`
- Composer 2
- MySQL 8 (or use the root `docker compose` which provides it)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# point .env at your MySQL, then:
php artisan migrate
php artisan serve      # http://localhost:8000
```

## Endpoints

| Method | Path                      | Description |
| ------ | ------------------------- | ----------- |
| `GET`  | `/api/products`           | Stored products, newest first. Query: `?per_page=` (max 100). Returns a paginated JSON resource. |
| `POST` | `/api/products/scrape`    | Scrape a page and upsert it. Body: `{ "url": "https://…" }` (optional; defaults to `scraper.target_url`). |
| `GET`  | `/up`                     | Framework health check. |

## Commands

```bash
php artisan scrape:product [url] [--dry-run]
```

Fetches the page, extracts title / price / image, and stores the result
(`--dry-run` prints without saving). Re-scraping the same URL updates the
existing row (upsert on `source_url`).

## How the scraper works

```
ScrapeProductCommand / ProductController
        │
        ▼
ProductScraper ──> HttpClientFactory ──> Guzzle client (rotated User-Agent + proxy)
        │                                        │
        │            ProxyProvider  ◀────────────┘  next() / report(ok)
        │              (HttpProxyProvider ─> Go proxy-service, or NullProxyProvider)
        ▼
DomCrawler + CSS selectors ──> PriceParser ──> ScrapedProduct (DTO)
        │
        ▼
StoreScrapedProduct ──> Product::updateOrCreate(source_url)
```

- `App\Services\Scraping\ProductScraper` — orchestrates fetch → parse → DTO,
  retries `SCRAPER_RETRIES` times, reporting each failing proxy back.
- `App\Services\Scraping\PriceParser` — currency string → float.
- `App\Services\Scraping\UserAgentRotator` — round-robins `config('scraper.user_agents')`.
- `App\Services\Proxy\*` — `ProxyProvider` interface with an HTTP-backed
  implementation (the Go service) and a null implementation for direct connections.
- Bindings live in `App\Providers\ScrapingServiceProvider`.

## Configuration

All scraper knobs are in `config/scraper.php`, driven by the `SCRAPER_*` and
`PROXY_SERVICE_*` variables documented in `.env.example`.

## Tests

```bash
php artisan test        # uses an in-memory SQLite db; no MySQL needed
vendor/bin/pint         # code style (Laravel preset)
```

Guzzle is stubbed with `MockHandler` in feature tests, so the suite exercises the
real client, DOM parsing and persistence without hitting the network.
