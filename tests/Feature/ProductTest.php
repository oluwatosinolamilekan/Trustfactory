<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronic products',
        ]);
    }

    public function test_guest_cannot_access_products_index(): void
    {
        $response = $this->get(route('products.index'));
        
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_products_index(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('products.index'));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Products/Index'));
    }

    public function test_products_index_returns_paginated_products(): void
    {
        // Create 15 products
        Product::factory()->count(15)->create([
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('products.index'));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('products', 10) // Default pagination is 10
                ->has('pagination')
                ->has('filters')
                ->has('categories')
                ->has('cartCount')
        );
    }

    public function test_products_can_be_filtered_by_search(): void
    {
        Product::create([
            'name' => 'iPhone 15',
            'category_id' => $this->category->id,
            'description' => 'Latest iPhone',
            'price' => 999.99,
            'stock_quantity' => 10,
        ]);

        Product::create([
            'name' => 'Samsung Galaxy',
            'category_id' => $this->category->id,
            'description' => 'Android phone',
            'price' => 799.99,
            'stock_quantity' => 15,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('products.index', ['search' => 'iPhone']));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('products', 1)
                ->where('filters.search', 'iPhone')
        );
    }

    public function test_products_can_be_filtered_by_category(): void
    {
        $clothingCategory = Category::create([
            'name' => 'Clothing',
            'slug' => 'clothing',
            'description' => 'Clothing items',
        ]);

        Product::create([
            'name' => 'Laptop',
            'category_id' => $this->category->id,
            'description' => 'Gaming laptop',
            'price' => 1299.99,
            'stock_quantity' => 5,
        ]);

        Product::create([
            'name' => 'T-Shirt',
            'category_id' => $clothingCategory->id,
            'description' => 'Cotton t-shirt',
            'price' => 19.99,
            'stock_quantity' => 100,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('products.index', ['category' => 'clothing']));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('products', 1)
                ->where('filters.category', 'clothing')
        );
    }

    public function test_products_can_be_filtered_by_price_range(): void
    {
        Product::create([
            'name' => 'Cheap Item',
            'category_id' => $this->category->id,
            'description' => 'Low price',
            'price' => 10.00,
            'stock_quantity' => 50,
        ]);

        Product::create([
            'name' => 'Expensive Item',
            'category_id' => $this->category->id,
            'description' => 'High price',
            'price' => 1000.00,
            'stock_quantity' => 5,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('products.index', ['min_price' => 50, 'max_price' => 500]));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->where('filters.min_price', '50')
                ->where('filters.max_price', '500')
        );
    }

    public function test_products_can_be_sorted(): void
    {
        Product::create([
            'name' => 'Product A',
            'category_id' => $this->category->id,
            'description' => 'First product',
            'price' => 100.00,
            'stock_quantity' => 10,
        ]);

        Product::create([
            'name' => 'Product B',
            'category_id' => $this->category->id,
            'description' => 'Second product',
            'price' => 50.00,
            'stock_quantity' => 20,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('products.index', ['sort_by' => 'price', 'sort_order' => 'asc']));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->where('filters.sort_by', 'price')
                ->where('filters.sort_order', 'asc')
        );
    }

    public function test_authenticated_user_can_view_product_details(): void
    {
        $product = Product::create([
            'name' => 'Test Product',
            'category_id' => $this->category->id,
            'description' => 'Test description',
            'price' => 99.99,
            'stock_quantity' => 10,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('products.show', $product));
        
        $response->assertStatus(200);
        // Just verify we get a response with product data
        $response->assertInertia(fn ($page) => 
            $page->has('product')
                ->where('product.name', 'Test Product')
                ->where('product.price', '99.99')
        );
    }

    public function test_guest_cannot_view_product_details(): void
    {
        $product = Product::create([
            'name' => 'Test Product',
            'category_id' => $this->category->id,
            'description' => 'Test description',
            'price' => 99.99,
            'stock_quantity' => 10,
        ]);

        $response = $this->get(route('products.show', $product));
        
        $response->assertRedirect(route('login'));
    }

    public function test_viewing_nonexistent_product_returns_404(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('products.show', 999));
        
        $response->assertStatus(404);
    }

    public function test_products_index_includes_cart_count(): void
    {
        $product = Product::create([
            'name' => 'Test Product',
            'category_id' => $this->category->id,
            'description' => 'Test description',
            'price' => 99.99,
            'stock_quantity' => 10,
        ]);

        // Add items to cart
        $this->user->cartItems()->create([
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('products.index'));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->where('cartCount', 3)
        );
    }
}

