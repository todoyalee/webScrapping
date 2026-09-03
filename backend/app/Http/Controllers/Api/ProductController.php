<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScrapeProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Scraping\Exceptions\ScrapeException;
use App\Services\Scraping\ProductScraper;
use App\Services\Scraping\StoreScrapedProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * GET /api/products — stored products, newest first, paginated.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', 24);

        $products = Product::query()
            ->latest()
            ->paginate(min($perPage, 100));

        return ProductResource::collection($products);
    }

    /**
     * POST /api/products/scrape — scrape a product page and store the result.
     */
    public function scrape(
        ScrapeProductRequest $request,
        ProductScraper $scraper,
        StoreScrapedProduct $store,
    ): JsonResponse {
        try {
            $product = $store($scraper->scrape($request->targetUrl()));
        } catch (ScrapeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return ProductResource::make($product)
            ->response()
            ->setStatusCode($product->wasRecentlyCreated ? 201 : 200);
    }
}
