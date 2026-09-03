<?php

namespace Database\Seeders;

use App\Services\Scraping\Exceptions\ScrapeException;
use App\Services\Scraping\ProductScraper;
use App\Services\Scraping\StoreScrapedProduct;
use Illuminate\Database\Seeder;

/**
 * Populates the grid on first boot by running a handful of URLs through the real
 * scraping pipeline. Safe to re-run: each URL upserts on source_url.
 */
class ProductSeeder extends Seeder
{
    /** @var list<string> */
    private const URLS = [
        'https://books.toscrape.com/catalogue/a-light-in-the-attic_1000/index.html',
        'https://books.toscrape.com/catalogue/tipping-the-velvet_999/index.html',
        'https://books.toscrape.com/catalogue/soumission_998/index.html',
        'https://books.toscrape.com/catalogue/sharp-objects_997/index.html',
        'https://books.toscrape.com/catalogue/sapiens-a-brief-history-of-humankind_996/index.html',
        'https://books.toscrape.com/catalogue/the-requiem-red_995/index.html',
    ];

    public function run(ProductScraper $scraper, StoreScrapedProduct $store): void
    {
        foreach (self::URLS as $url) {
            try {
                $store($scraper->scrape($url));
            } catch (ScrapeException $e) {
                $this->command?->warn("Skipped {$url}: {$e->getMessage()}");
            }
        }
    }
}
