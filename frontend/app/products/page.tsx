import { getProducts, type Product } from "@/lib/api";
import { ProductGrid } from "./product-grid";

// Always render fresh: this page mirrors a live scrape feed.
export const dynamic = "force-dynamic";

export default async function ProductsPage() {
  let initialProducts: Product[] = [];
  let initialError: string | null = null;

  try {
    initialProducts = await getProducts();
  } catch (e) {
    initialError = e instanceof Error ? e.message : "Failed to load products";
  }

  return (
    <main className="mx-auto w-full max-w-6xl px-4 py-10">
      <header className="mb-8">
        <h1 className="text-2xl font-semibold tracking-tight">Scraped products</h1>
        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
          Pulled from the Laravel API · auto-refreshes every 30 seconds
        </p>
      </header>

      <ProductGrid initialProducts={initialProducts} initialError={initialError} />
    </main>
  );
}
