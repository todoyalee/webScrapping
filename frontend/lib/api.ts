export type Product = {
  id: number;
  title: string;
  price: number | null;
  image_url: string | null;
  source_url: string | null;
  created_at: string | null;
};

type ProductsResponse = {
  data: Product[];
  meta?: { current_page: number; last_page: number; total: number };
};

/**
 * Base URL of the Laravel API.
 * - On the server (SSR, inside Docker) the backend is reachable by service name.
 * - In the browser it must be a public URL.
 */
function apiBaseUrl(): string {
  if (typeof window === "undefined") {
    return process.env.API_BASE_URL ?? "http://localhost:8000";
  }
  return process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000";
}

export async function getProducts(perPage = 48): Promise<Product[]> {
  const res = await fetch(
    `${apiBaseUrl()}/api/products?per_page=${perPage}`,
    { cache: "no-store", headers: { Accept: "application/json" } },
  );

  if (!res.ok) {
    throw new Error(`API responded ${res.status}`);
  }

  const body = (await res.json()) as ProductsResponse;
  return body.data;
}
