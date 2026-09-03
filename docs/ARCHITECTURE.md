# Architecture

## Overview

Three services, one job: fetch product pages without looking like a single
robot, store what comes back, and show it.

```
┌─────────────┐   GET /api/products (poll 30s)   ┌──────────────┐
│  Next.js    │ ───────────────────────────────▶ │   Laravel    │
│  frontend   │ ◀─────────────────────────────── │   backend    │
│  /products  │        JSON (paginated)          │              │
└─────────────┘                                  │  ┌────────┐  │
                                                 │  │Scraper │  │
┌─────────────┐   GET  /proxy                    │  │service │  │
│  Go proxy-  │ ◀────────────────────────────────┼──┤        │  │
│  service    │   POST /proxies/report           │  └───┬────┘  │
│  (pool)     │ ────────────────────────────────▶│      │Guzzle │
└─────────────┘                                  └──────┼───────┘
                                                        ▼
                                                 target site
                                                        │
                                                  ┌─────▼─────┐
                                                  │   MySQL   │
                                                  └───────────┘
```

## Request flow for one scrape

1. `scrape:product` (CLI, or `POST /api/products/scrape`, or the scheduler)
   calls `ProductScraper::scrape($url)`.
2. `ProductScraper` asks `ProxyProvider::next()` for an upstream proxy. With the
   Go service enabled this is an HTTP call to `GET /proxy`; otherwise a no-op
   that returns `null` (direct connection).
3. `HttpClientFactory` builds a fresh Guzzle client with the **next User-Agent**
   from the rotator and, if present, the `proxy` option.
4. The page is fetched. On a transport error the proxy is reported
   (`POST /proxies/report {ok:false}`) and the request is retried
   (`SCRAPER_RETRIES` times) with the next proxy; on success `{ok:true}`.
5. `Symfony\DomCrawler` + CSS selectors pull out title / price / image.
   `PriceParser` normalises the price string to a float; the image URL is
   resolved to absolute against the page URL.
6. `StoreScrapedProduct` upserts a `Product` row keyed on `source_url`.

## Why three processes

| Concern | Where | Reason |
| ------- | ----- | ------ |
| Header fingerprint rotation | Laravel (`UserAgentRotator`) | Cheap, per-request, no network hop. |
| IP rotation & proxy health | Go `proxy-service` | Proxy lists change constantly and rotation is stateful (round-robin cursor, failure counts, cooldowns). Isolating it means we can restart/scale/replace the pool without redeploying the scraper, and a proxy outage degrades to a direct connection instead of an error. Go keeps it a single ~7 MB static binary with no runtime deps. |
| Presentation | Next.js | Server-rendered first paint + client polling is the natural fit for a "live feed" page. |

## Key design decisions

- **Config over code.** Target URL, CSS selectors, timeouts, retry count, the
  User-Agent list and every proxy setting are environment variables
  (`config/scraper.php`). Re-targeting the scraper is a `.env` change.
- **Interfaces at the seams.** `HttpClientFactory` and `ProxyProvider` are
  interfaces. Tests bind fakes (Guzzle `MockHandler`, an in-memory proxy spy) so
  the suite runs offline yet still exercises real parsing and persistence.
- **Pure, tested core.** `PriceParser`, `UserAgentRotator` and the Go `proxy.Pool`
  are plain units with table-driven tests. Controllers and `main.go` only wire
  dependencies together.
- **Upsert on `source_url`.** Re-scraping refreshes a row instead of duplicating
  it, so the scheduler can run forever without bloating the table.
- **Graceful shutdown** in the Go service (`http.Server.Shutdown` on SIGTERM) so
  `docker compose down` is clean.
- **No background workers in Go.** Proxy health advances lazily on each
  `Next()`/`Report()` call, which removes a goroutine and makes behaviour
  deterministic under a fake clock in tests.

## Data model

`products`: `id`, `title`, `price` (decimal 10,2, nullable), `image_url`,
`source_url` (unique, nullable — used for upsert), `created_at`, `updated_at`.

## Trade-offs / what I'd add next

- The proxy pool is in-memory; a restart forgets health state and runtime-added
  proxies. Fine for this scope — Redis or a small table would fix it.
- Scraping is synchronous. At volume it should be a queued job
  (`ShouldQueue`) with a rate limiter per target host.
- No auth on the API — out of scope, but `auth:sanctum` on the write route is the
  obvious first step.
- Selector-based parsing is brittle against real stores; a per-site adapter
  class keyed off the hostname would be the clean extension point.
