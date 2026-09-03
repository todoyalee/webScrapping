<?php

namespace App\Services\Scraping;

use App\Models\Product;

/**
 * Persists a scrape result, upserting on source_url so re-scraping a page
 * refreshes the existing row instead of creating duplicates.
 */
final class StoreScrapedProduct
{
    public function __invoke(ScrapedProduct $scraped): Product
    {
        return Product::updateOrCreate(
            ['source_url' => $scraped->sourceUrl],
            $scraped->toAttributes(),
        );
    }
}
