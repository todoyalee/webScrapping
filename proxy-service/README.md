# proxy-service

A small Go microservice that manages a pool of upstream proxies and hands them
to the Laravel scraper in round-robin order. Standard library only — no external
dependencies.

## Why it exists

Rotating the `User-Agent` header (done in Laravel) varies the client
fingerprint, but every request still leaves from the same IP. This service owns
the **IP-rotation** half: the scraper asks for "the next proxy" before each
request and reports the outcome, so proxies that start failing are quarantined
and taken out of rotation automatically.

## API

| Method & path          | Body                              | Response |
| ---------------------- | --------------------------------- | -------- |
| `GET /healthz`         | –                                 | `{ "status": "ok", "proxies": 3 }` |
| `GET /proxy`           | –                                 | `{ "proxy": "http://host:port" }` (next in rotation) |
| `GET /proxies`         | –                                 | `{ "count": 3, "proxies": [ { "url", "healthy", "failures" } ] }` |
| `POST /proxies`        | `{ "proxy": "http://host:port" }` | `201` – add a proxy at runtime |
| `POST /proxies/report` | `{ "proxy": "...", "ok": false }` | `204` – record a success/failure |

If `PROXY_SERVICE_TOKEN` is set, every route except `/healthz` requires
`Authorization: Bearer <token>`.

## Configuration (environment)

| Variable               | Default   | Meaning |
| ---------------------- | --------- | ------- |
| `PROXY_SERVICE_ADDR`   | `:9000`   | Listen address |
| `PROXY_SEEDS`          | –         | Comma-separated proxy URLs to start with |
| `PROXY_SEED_FILE`      | –         | Path to a file with one proxy URL per line |
| `PROXY_SERVICE_TOKEN`  | –         | Optional bearer token |
| `PROXY_MAX_FAILURES`   | `3`       | Consecutive failures before a proxy is quarantined |
| `PROXY_COOLDOWN`       | `60s`     | How long a quarantined proxy is skipped |

## Run

```bash
# locally
PROXY_SEEDS="http://198.51.100.10:8080,http://198.51.100.11:3128" go run .

# tests
go test ./... -race

# container
docker build -t proxyscrape/proxy-service .
docker run -p 9000:9000 -e PROXY_SEEDS="http://198.51.100.10:8080" proxyscrape/proxy-service
```

## Design notes

- **Rotation** is a mutex-guarded index into a slice; `Next()` walks at most one
  full lap and skips quarantined entries.
- **Health** is tracked per proxy: N consecutive failures set a `quarantined_until`
  timestamp; a success resets the counter. No background goroutine — state moves
  forward lazily on each `Next()`/`Report()` call, which keeps it trivial to test
  with a fake clock.
- **Graceful shutdown** on `SIGINT`/`SIGTERM` via `http.Server.Shutdown`.
- The pool is swappable behind an interface so the HTTP layer is tested in
  isolation.
