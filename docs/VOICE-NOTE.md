# One-minute voice note — script

Read at a normal pace this runs ~60 seconds.

---

Hi Amr, here's a quick walkthrough of my ProxyScrape solution.

**Architecture.** Three services in one repo. A Laravel backend does the
scraping and owns the database. A Next.js frontend shows the products. And a
small Go microservice manages a pool of proxies. They talk over HTTP.

**Design decisions.** I split rotation into two layers. Laravel rotates the
User-Agent header per request — that's cheap and local. The Go service owns IP
rotation, because a proxy list is stateful and changes constantly: it does
round-robin with health tracking, so a proxy that starts failing gets
quarantined and skipped for a cooldown. Keeping it separate means the pool can
restart or scale without touching the scraper, and if every proxy is down the
scraper just falls back to a direct connection instead of erroring.

Everything is config-driven — target URL, CSS selectors, timeouts, the
User-Agent list — so pointing it at Jumia instead of the sandbox site is an
environment change, not a code change. The parsing, price normalisation and pool
rotation are plain classes with unit tests; the controllers and main function
only wire things together.

**Integration.** The scraper asks the Go service for a proxy before each Guzzle
request and reports back success or failure. It stores results with an upsert on
the source URL, so re-scraping refreshes rather than duplicates. The frontend
server-renders the first list, then polls the products endpoint every 30
seconds.

**Challenges.** The main one was making the price parser handle every currency
format — pound, dollar with comma thousands, euro with comma decimals — which
is a table-driven test now. The other was keeping the Go service dependency-free
and deterministic: health state advances lazily on each call, so there's no
background goroutine and I can test it with a fake clock.

The README has one command to run the whole stack. Thanks!
