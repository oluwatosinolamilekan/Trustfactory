<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Category;
use App\Repositories\ProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected ProductRepository $repository;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = new ProductRepository();
        $this->category = Category::factory()->create();
    }

    public function test_find_by_id_returns_product(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $found = $this->repository->findById($product->id);

        $this->assertNotNull($found);
        $this->assertEquals($product->id, $found->id);
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $found = $this->repository->findById(999);

        $this->assertNull($found);
    }

    public function test_find_by_id_or_fail_returns_product(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $found = $this->repository->findByIdOrFail($product->id);

        $this->assertNotNull($found);
        $this->assertEquals($product->id, $found->id);
    }

    public function test_find_by_id_or_fail_throws_exception_when_not_found(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->repository->findByIdOrFail(999);
    }

    public function test_get_all_returns_all_products(): void
    {
        Product::factory()->count(5)->create([
            'category_id' => $this->category->id,
        ]);

        $products = $this->repository->getAll();

        $this->assertCount(5, $products);
    }

    public function test_get_paginated_returns_paginated_results(): void
    {
        Product::factory()->count(20)->create([
            'category_id' => $this->category->id,
        ]);

        $paginated = $this->repository->getPaginated(10);

        $this->assertCount(10, $paginated);
        $this->assertEquals(20, $paginated->total());
    }

    public function test_update_stock_changes_quantity(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 10,
        ]);

        $result = $this->repository->updateStock($product, 25);

        $this->assertTrue($result);
        $product->refresh();
        $this->assertEquals(25, $product->stock_quantity);
    }

    public function test_decrease_stock_reduces_quantity(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 20,
        ]);

        $result = $this->repository->decreaseStock($product, 5);

        $this->assertTrue($result);
        $product->refresh();
        $this->assertEquals(15, $product->stock_quantity);
    }

    public function test_has_sufficient_stock_returns_true_when_enough(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 10,
        ]);

        $result = $this->repository->hasSufficientStock($product, 5);

        $this->assertTrue($result);
    }

    public function test_has_sufficient_stock_returns_false_when_not_enough(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 10,
        ]);

        $result = $this->repository->hasSufficientStock($product, 15);

        $this->assertFalse($result);
    }

    public function test_has_sufficient_stock_returns_true_for_exact_amount(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 10,
        ]);

        $result = $this->repository->hasSufficientStock($product, 10);

        $this->assertTrue($result);
    }

    public function test_is_low_stock_returns_correct_value(): void
    {
        $lowStockProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 5,
        ]);

        $result = $this->repository->isLowStock($lowStockProduct);

        $this->assertTrue($result);
    }

    public function test_get_filtered_products_applies_search_filter(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'iPhone 15',
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Samsung Galaxy',
        ]);

        $results = $this->repository->getFilteredProducts(search: 'iPhone');

        $this->assertEquals(1, $results->total());
        $this->assertEquals('iPhone 15', $results->first()->name);
    }

    public function test_get_filtered_products_applies_category_filter(): void
    {
        $electronicsCategory = $this->category;
        $clothingCategory = Category::factory()->create(['slug' => 'clothing']);

        Product::factory()->create([
            'category_id' => $electronicsCategory->id,
            'name' => 'Laptop',
        ]);

        Product::factory()->create([
            'category_id' => $clothingCategory->id,
            'name' => 'T-Shirt',
        ]);

        $results = $this->repository->getFilteredProducts(category: 'clothing');

        $this->assertEquals(1, $results->total());
        $this->assertEquals('T-Shirt', $results->first()->name);
    }

    public function test_get_filtered_products_applies_price_filters(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 50.00,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 150.00,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 250.00,
        ]);

        $results = $this->repository->getFilteredProducts(
            minPrice: '100',
            maxPrice: '200'
        );

        $this->assertEquals(1, $results->total());
        $this->assertEquals(150.00, (float)$results->first()->price);
    }

    public function test_get_filtered_products_applies_sorting(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Zebra Product',
            'price' => 50.00,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Alpha Product',
            'price' => 150.00,
        ]);

        $resultsByName = $this->repository->getFilteredProducts(
            sortBy: 'name',
            sortOrder: 'asc'
        );

        $this->assertEquals('Alpha Product', $resultsByName->first()->name);

        $resultsByPrice = $this->repository->getFilteredProducts(
            sortBy: 'price',
            sortOrder: 'desc'
        );

        $this->assertEquals(150.00, (float)$resultsByPrice->first()->price);
    }

    public function test_get_filtered_products_returns_paginated_results(): void
    {
        Product::factory()->count(25)->create([
            'category_id' => $this->category->id,
        ]);

        $results = $this->repository->getFilteredProducts(perPage: 10);

        $this->assertCount(10, $results);
        $this->assertEquals(25, $results->total());
    }

    public function test_get_filtered_products_loads_category_relationship(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $results = $this->repository->getFilteredProducts();

        $this->assertTrue($results->first()->relationLoaded('category'));
    }
}

