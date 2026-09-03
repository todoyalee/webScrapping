<?php

namespace Tests\Unit;

use App\Services\Scraping\PriceParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PriceParserTest extends TestCase
{
    #[DataProvider('prices')]
    public function test_it_normalises_price_strings(?string $raw, ?float $expected): void
    {
        $this->assertSame($expected, PriceParser::parse($raw));
    }

    public static function prices(): array
    {
        return [
            'gbp' => ['£51.77', 51.77],
            'usd with thousands' => ['$1,024.99', 1024.99],
            'euro comma decimal' => ['1.299,00 EGP', 1299.00],
            'plain integer' => ['750', 750.0],
            'dot thousands only' => ['1.299', 1299.0],
            'trailing text' => ['KES 2,500 only', 2500.0],
            'null' => [null, null],
            'no digits' => ['Out of stock', null],
        ];
    }
}
