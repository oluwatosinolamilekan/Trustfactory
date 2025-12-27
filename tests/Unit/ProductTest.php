<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->category = Category::factory()->create();
    }

    public function test_product_belongs_to_category(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertEquals($this->category->id, $product->category->id);
    }

    public function test_is_low_stock_returns_true_when_stock_at_threshold(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 10,
        ]);

        $this->assertTrue($product->isLowStock(10));
    }

    public function test_is_low_stock_returns_true_when_stock_below_threshold(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 5,
        ]);

        $this->assertTrue($product->isLowStock(10));
    }

    public function test_is_low_stock_returns_false_when_stock_above_threshold(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 15,
        ]);

        $this->assertFalse($product->isLowStock(10));
    }

    public function test_is_low_stock_returns_false_when_out_of_stock(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 0,
        ]);

        $this->assertFalse($product->isLowStock(10));
    }

    public function test_is_out_of_stock_returns_true_when_stock_is_zero(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 0,
        ]);

        $this->assertTrue($product->isOutOfStock());
    }

    public function test_is_out_of_stock_returns_false_when_stock_available(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 5,
        ]);

        $this->assertFalse($product->isOutOfStock());
    }

    public function test_apply_filters_scope_filters_by_search(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'iPhone 15 Pro',
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Samsung Galaxy',
        ]);

        $results = Product::applyFilters(search: 'iPhone')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('iPhone 15 Pro', $results->first()->name);
    }

    public function test_apply_filters_scope_filters_by_category(): void
    {
        $electronicsCategory = $this->category;
        $clothingCategory = Category::factory()->create([
            'slug' => 'clothing',
        ]);

        Product::factory()->create([
            'category_id' => $electronicsCategory->id,
            'name' => 'Laptop',
        ]);

        Product::factory()->create([
            'category_id' => $clothingCategory->id,
            'name' => 'T-Shirt',
        ]);

        $results = Product::applyFilters(categorySlug: 'clothing')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('T-Shirt', $results->first()->name);
    }

    public function test_apply_filters_scope_filters_by_min_price(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 50.00,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 150.00,
        ]);

        $results = Product::applyFilters(minPrice: 100)->get();

        $this->assertCount(1, $results);
        $this->assertEquals(150.00, (float)$results->first()->price);
    }

    public function test_apply_filters_scope_filters_by_max_price(): void
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 50.00,
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 150.00,
        ]);

        $results = Product::applyFilters(maxPrice: 100)->get();

        $this->assertCount(1, $results);
        $this->assertEquals(50.00, (float)$results->first()->price);
    }

    public function test_apply_filters_scope_combines_multiple_filters(): void
    {
        $electronicsCategory = $this->category;
        $clothingCategory = Category::factory()->create([
            'slug' => 'clothing',
        ]);

        Product::factory()->create([
            'category_id' => $electronicsCategory->id,
            'name' => 'Cheap Phone',
            'price' => 50.00,
        ]);

        Product::factory()->create([
            'category_id' => $electronicsCategory->id,
            'name' => 'Expensive Phone',
            'price' => 1000.00,
        ]);

        Product::factory()->create([
            'category_id' => $clothingCategory->id,
            'name' => 'Phone Case',
            'price' => 200.00,
        ]);

        $results = Product::applyFilters(
            search: 'Phone',
            categorySlug: $electronicsCategory->slug,
            minPrice: 100,
            maxPrice: 500
        )->get();

        $this->assertCount(0, $results);
    }

    public function test_price_is_cast_to_decimal(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 99.99,
        ]);

        $this->assertIsString($product->price);
        $this->assertEquals('99.99', $product->price);
    }

    public function test_product_has_cart_items_relationship(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $product->cartItems);
    }

    public function test_product_has_order_items_relationship(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $product->orderItems);
    }
}

