<?php

namespace App\Console\Commands;

use App\Services\Scraping\Exceptions\ScrapeException;
use App\Services\Scraping\ProductScraper;
use App\Services\Scraping\StoreScrapedProduct;
use Illuminate\Console\Command;

class ScrapeProductCommand extends Command
{
    protected $signature = 'scrape:product
        {url? : Product page URL (defaults to config("scraper.target_url"))}
        {--dry-run : Scrape and print the result without storing it}';

    protected $description = 'Scrape a product page and store the title, price and image';

    public function handle(ProductScraper $scraper, StoreScrapedProduct $store): int
    {
        $url = $this->argument('url') ?? config('scraper.target_url');

        $this->info("Scraping {$url}");

        try {
            $scraped = $scraper->scrape($url);
        } catch (ScrapeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], [
            ['title', $scraped->title],
            ['price', $scraped->price ?? '—'],
            ['image_url', $scraped->imageUrl ?? '—'],
        ]);

        if ($this->option('dry-run')) {
            $this->comment('Dry run: nothing stored.');

            return self::SUCCESS;
        }

        $product = $store($scraped);
        $this->info("Stored product #{$product->id}.");

        return self::SUCCESS;
    }
}
