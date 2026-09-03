# ProxyScrape

A small web-scraping service:

| Component        | Stack                     | Responsibility |
| ---------------- | ------------------------- | -------------- |
| `backend/`       | Laravel 13 · PHP 8.4 · MySQL | Scrapes product pages with Guzzle, rotates User-Agents, stores results, exposes `GET /api/products` |
| `proxy-service/` | Go 1.23 (stdlib only)     | Round-robin pool of upstream proxies with health tracking; the scraper borrows an IP per request |
| `frontend/`      | Next.js 16 · React 19 · Tailwind | `/products` grid, server-rendered then polled every 30 s |

```
                 GET /proxy                 GET / (HTML page)
  ┌───────────┐  POST /proxies/report  ┌───────────┐   ┌───────────┐
  │  proxy-   │◀──────────────────────▶│  Laravel  │◀──│  Next.js  │
  │  service  │                        │  backend  │   │  frontend │
  │   (Go)    │                        │           │──▶│  /products│
  └───────────┘                        └─────┬─────┘   └───────────┘
                                             │ Eloquent
                                       ┌─────▼─────┐
                                       │   MySQL   │
                                       └───────────┘
```

## Quick start (Docker)

```bash
docker compose up --build
```

Then open:

- Frontend — <http://localhost:3000/products>
- API — <http://localhost:8000/api/products>
- Proxy service — <http://localhost:9000/proxies>

`docker compose up` migrates the database and seeds a handful of products
through the real scraper on boot, so the grid is populated immediately. A
scheduler container re-scrapes every 15 minutes.

## Run a scrape manually

```bash
# default target (books.toscrape.com sandbox)
docker compose exec backend php artisan scrape:product

# any product URL
docker compose exec backend php artisan scrape:product "https://books.toscrape.com/catalogue/sharp-objects_997/index.html"

# or via the API
curl -X POST http://localhost:8000/api/products/scrape \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://books.toscrape.com/catalogue/sharp-objects_997/index.html"}'
```

## Local development (without Docker)

Each service has its own README with prerequisites and commands:

- [backend/README.md](backend/README.md)
- [proxy-service/README.md](proxy-service/README.md)
- [frontend/README.md](frontend/README.md)

## Scraping target

The scraper defaults to [books.toscrape.com](https://books.toscrape.com), a
sandbox built for scraping practice, so the demo is deterministic. It is
**target-agnostic**: set `SCRAPER_TARGET_URL` and the `SCRAPER_SELECTOR_*`
variables (see `backend/.env.example`) to point it at Amazon, Jumia, etc. Prices
in any common format (`£51.77`, `1.299,00 EGP`, `$1,024.99`) are normalised to a
number.

## Tests

```bash
cd backend       && php artisan test     # 18 tests: API, scraper, price parser, proxy retry
cd proxy-service && go test ./... -race  # pool rotation, quarantine, HTTP API, auth
cd frontend      && npm run lint && npm run build
```

CI runs all three on every push and pull request (`.github/workflows/ci.yml`).

## Design decisions

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the full write-up. In short:

- **Two layers of rotation.** Header rotation (User-Agent) lives in Laravel;
  IP rotation lives in the Go service. Keeping them separate means the proxy
  pool can be scaled, restarted or swapped without touching the scraper.
- **Graceful degradation.** If the proxy service is down or every proxy is
  quarantined, the scraper logs it and connects directly rather than failing.
- **Config over code.** Target URL, selectors, timeouts, User-Agent list and
  proxy behaviour are all environment-driven.
- **Thin HTTP, testable core.** Parsing, price normalisation and pool rotation
  are plain classes with unit tests; controllers and `main.go` just wire them.
