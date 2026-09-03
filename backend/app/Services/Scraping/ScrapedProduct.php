<?php

namespace App\Services\Scraping;

/**
 * Immutable value object holding the result of a single scrape.
 */
final readonly class ScrapedProduct
{
    public function __construct(
        public string $title,
        public ?float $price,
        public ?string $imageUrl,
        public string $sourceUrl,
    ) {}

    /**
     * @return array{title: string, price: float|null, image_url: string|null, source_url: string}
     */
    public function toAttributes(): array
    {
        return [
            'title' => $this->title,
            'price' => $this->price,
            'image_url' => $this->imageUrl,
            'source_url' => $this->sourceUrl,
        ];
    }
}
