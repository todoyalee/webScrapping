<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_stored_products_newest_first(): void
    {
        $old = Product::factory()->create(['created_at' => now()->subDay()]);
        $new = Product::factory()->create(['created_at' => now()]);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.1.id', $old->id)
            ->assertJsonStructure([
                'data' => [['id', 'title', 'price', 'image_url', 'source_url', 'created_at']],
                'links',
                'meta' => ['current_page', 'total'],
            ]);
    }

    public function test_it_paginates(): void
    {
        Product::factory()->count(30)->create();

        $this->getJson('/api/products?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 30);
    }

    public function test_price_is_serialised_as_a_number(): void
    {
        Product::factory()->create(['price' => 12.5]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.price', 12.5);
    }
}
