# frontend — Next.js products page

## Prerequisites

- Node.js 22

## Setup

```bash
npm install
cp .env.example .env.local   # set NEXT_PUBLIC_API_BASE_URL if the API is not on :8000
npm run dev                  # http://localhost:3000  ->  redirects to /products
```

## `/products`

- **Server component** (`app/products/page.tsx`) does the first fetch, so the
  page arrives populated with no loading flash. If the API is down at request
  time it renders with an error banner instead of throwing.
- **Client component** (`app/products/product-grid.tsx`) reconciles on mount and
  then polls `GET /api/products` every **30 seconds** (`setInterval`, cleared on
  unmount). A "Refresh now" button and a "last updated" timestamp are exposed.
- Responsive grid: 2 columns on mobile → 4 on desktop (Tailwind).
- Product images come from arbitrary scraped hosts, so a plain lazy-loaded
  `<img>` is used rather than `next/image` (which needs a domain allow-list).

## Environment

| Variable                   | Used by | Default |
| -------------------------- | ------- | ------- |
| `NEXT_PUBLIC_API_BASE_URL` | Browser (inlined at build) | `http://localhost:8000` |
| `API_BASE_URL`             | SSR (runtime) | falls back to the public default; set to `http://backend:8000` under docker-compose |

## Scripts

```bash
npm run dev     # dev server
npm run build   # production build (output: standalone)
npm run lint    # eslint (next/core-web-vitals + typescript)
```
