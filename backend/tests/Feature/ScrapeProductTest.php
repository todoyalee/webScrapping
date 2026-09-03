<?php

namespace Tests\Feature;

use App\Services\Http\HttpClientFactory;
use App\Services\Proxy\ProxyProvider;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrapeProductTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://books.toscrape.com/catalogue/a-light-in-the-attic_1000/index.html';

    protected function setUp(): void
    {
        parent::setUp();

        // Serve the local fixture instead of hitting the network, but still
        // exercise the real Guzzle client, DomCrawler parsing and persistence.
        $html = file_get_contents(base_path('tests/Fixtures/product.html'));
        $mock = new MockHandler([new Response(200, [], $html)]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $this->app->bind(HttpClientFactory::class, fn () => new class($client) implements HttpClientFactory
        {
            public function __construct(private ClientInterface $client) {}

            public function make(array $overrides = []): ClientInterface
            {
                return $this->client;
            }
        });
    }

    public function test_command_scrapes_and_stores_a_product(): void
    {
        $this->artisan('scrape:product', ['url' => self::URL])
            ->assertSuccessful();

        $this->assertDatabaseHas('products', [
            'title' => 'A Light in the Attic',
            'price' => 51.77,
            'source_url' => self::URL,
        ]);
    }

    public function test_dry_run_does_not_store(): void
    {
        $this->artisan('scrape:product', ['url' => self::URL, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_endpoint_scrapes_and_upserts_on_source_url(): void
    {
        $this->postJson('/api/products/scrape', ['url' => self::URL])->assertCreated();

        // Re-bind a second fixture response for the second call.
        $html = file_get_contents(base_path('tests/Fixtures/product.html'));
        $mock = new MockHandler([new Response(200, [], $html)]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $this->app->bind(HttpClientFactory::class, fn () => new class($client) implements HttpClientFactory
        {
            public function __construct(private ClientInterface $client) {}

            public function make(array $overrides = []): ClientInterface
            {
                return $this->client;
            }
        });

        $this->postJson('/api/products/scrape', ['url' => self::URL])->assertOk();

        $this->assertDatabaseCount('products', 1);
    }

    public function test_it_resolves_relative_image_urls_to_absolute(): void
    {
        $this->artisan('scrape:product', ['url' => self::URL])->assertSuccessful();

        $this->assertDatabaseHas('products', [
            'image_url' => 'https://books.toscrape.com/media/cache/fe/72/fe72f0532301ec28892ae79a629a293c.jpg',
        ]);
    }

    public function test_it_reports_a_failing_proxy_and_retries(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/product.html'));
        $mock = new MockHandler([
            new ConnectException('refused', new Request('GET', self::URL)),
            new Response(200, [], $html),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $this->app->bind(HttpClientFactory::class, fn () => new class($client) implements HttpClientFactory
        {
            public function __construct(private ClientInterface $client) {}

            public function make(array $overrides = []): ClientInterface
            {
                return $this->client;
            }
        });

        $spy = new class implements ProxyProvider
        {
            public array $reports = [];

            public function next(): ?string
            {
                return 'http://10.0.0.1:8080';
            }

            public function report(string $proxy, bool $ok): void
            {
                $this->reports[] = [$proxy, $ok];
            }
        };
        $this->app->instance(ProxyProvider::class, $spy);

        $this->artisan('scrape:product', ['url' => self::URL])->assertSuccessful();

        $this->assertSame(
            [['http://10.0.0.1:8080', false], ['http://10.0.0.1:8080', true]],
            $spy->reports,
        );
    }
}
