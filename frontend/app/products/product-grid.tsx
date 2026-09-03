"use client";

import { useCallback, useEffect, useState } from "react";
import { getProducts, type Product } from "@/lib/api";

const REFRESH_MS = 30_000;

type Props = {
  initialProducts: Product[];
  initialError: string | null;
};

export function ProductGrid({ initialProducts, initialError }: Props) {
  const [products, setProducts] = useState<Product[]>(initialProducts);
  const [error, setError] = useState<string | null>(initialError);
  const [refreshedAt, setRefreshedAt] = useState<Date | null>(
    initialError ? null : new Date(),
  );

  const refresh = useCallback(async () => {
    try {
      const next = await getProducts();
      setProducts(next);
      setError(null);
      setRefreshedAt(new Date());
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to refresh");
    }
  }, []);

  useEffect(() => {
    const tick = () => {
      void refresh();
    };

    const interval = setInterval(tick, REFRESH_MS);
    // Retry straight away only if the server-rendered fetch failed.
    const recovery = initialError ? setTimeout(tick, 0) : undefined;

    return () => {
      clearInterval(interval);
      if (recovery) clearTimeout(recovery);
    };
  }, [refresh, initialError]);

  return (
    <section>
      <div className="mb-4 flex items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
        <span aria-live="polite">
          {refreshedAt
            ? `Last updated ${refreshedAt.toLocaleTimeString()}`
            : "Waiting for data…"}
        </span>
        <button
          type="button"
          onClick={() => void refresh()}
          className="rounded border border-zinc-300 px-2 py-0.5 transition-colors hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
        >
          Refresh now
        </button>
      </div>

      {error && (
        <p
          role="alert"
          className="mb-4 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
        >
          {error}
        </p>
      )}

      {products.length === 0 && !error ? (
        <p className="text-sm text-zinc-500 dark:text-zinc-400">
          No products yet. Run{" "}
          <code className="rounded bg-zinc-200 px-1 py-0.5 dark:bg-zinc-800">
            php artisan scrape:product
          </code>{" "}
          in the backend.
        </p>
      ) : (
        <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </ul>
      )}
    </section>
  );
}

function ProductCard({ product }: { product: Product }) {
  return (
    <li className="flex flex-col overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
      <div className="aspect-square bg-zinc-100 dark:bg-zinc-800">
        {product.image_url ? (
          // Scraped images come from arbitrary hosts, so a plain <img> is used
          // instead of next/image (which needs an allow-list of domains).
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={product.image_url}
            alt={product.title}
            loading="lazy"
            className="h-full w-full object-contain"
          />
        ) : (
          <div className="flex h-full items-center justify-center text-xs text-zinc-400">
            no image
          </div>
        )}
      </div>
      <div className="flex flex-1 flex-col gap-1 p-3">
        <h2 className="line-clamp-2 text-sm font-medium">{product.title}</h2>
        <p className="mt-auto text-sm font-semibold text-emerald-600 dark:text-emerald-400">
          {product.price !== null
            ? product.price.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
              })
            : "—"}
        </p>
      </div>
    </li>
  );
}
