<?php

namespace App\Services\Scraping\Exceptions;

use RuntimeException;

class ScrapeException extends RuntimeException
{
    public static function requestFailed(string $url, string $reason): self
    {
        return new self("Failed to fetch [{$url}]: {$reason}");
    }

    public static function titleNotFound(string $url, string $selector): self
    {
        return new self("Could not find a title at [{$selector}] on [{$url}].");
    }
}
