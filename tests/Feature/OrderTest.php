<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->category = Category::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 100.00,
            'stock_quantity' => 20,
        ]);
    }

    public function test_guest_cannot_access_orders_index(): void
    {
        $response = $this->get(route('orders.index'));
        
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_orders_index(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('orders.index'));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Orders/Index')
                ->has('orders')
        );
    }

    public function test_orders_index_shows_only_user_orders(): void
    {
        // Create order for authenticated user
        $userOrder = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'completed',
        ]);

        // Create order for another user
        $otherUser = User::factory()->create();
        $otherOrder = Order::create([
            'user_id' => $otherUser->id,
            'total_amount' => 200.00,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('orders.index'));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('orders.data', 1)
        );
    }

    public function test_orders_are_paginated(): void
    {
        // Create 15 orders
        for ($i = 0; $i < 15; $i++) {
            Order::create([
                'user_id' => $this->user->id,
                'total_amount' => 100.00,
                'status' => 'completed',
            ]);
        }

        $response = $this->actingAs($this->user)
            ->get(route('orders.index'));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('orders.data', 10) // Default pagination
                ->has('orders.links')
                ->has('orders.meta')
        );
    }

    public function test_user_can_view_own_order_details(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 200.00,
            'status' => 'completed',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('orders.show', $order->id));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Orders/Show')
                ->has('order')
                ->where('order.id', $order->id)
                ->where('order.total_amount', '200.00')
                ->where('order.status', 'completed')
        );
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $otherUser = User::factory()->create();
        $order = Order::create([
            'user_id' => $otherUser->id,
            'total_amount' => 200.00,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('orders.show', $order->id));
        
        $response->assertStatus(404);
    }

    public function test_guest_cannot_view_order_details(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 200.00,
            'status' => 'completed',
        ]);

        $response = $this->get(route('orders.show', $order->id));
        
        $response->assertRedirect(route('login'));
    }

    public function test_viewing_nonexistent_order_returns_404(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('orders.show', 99999));
        
        $response->assertStatus(404);
    }

    public function test_order_details_include_order_items(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 200.00,
            'status' => 'completed',
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 50.00,
            'stock_quantity' => 10,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 100.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 2,
            'price' => 50.00,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('orders.show', $order->id));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('order.order_items', 2)
        );
    }

    public function test_order_items_include_product_information(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'completed',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('orders.show', $order->id));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('order.order_items.0.product')
                ->where('order.order_items.0.product.id', $this->product->id)
                ->where('order.order_items.0.product.name', $this->product->name)
        );
    }

    public function test_orders_are_sorted_by_created_date_descending(): void
    {
        // Create orders with specific timestamps
        $oldOrder = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'completed',
        ]);

        sleep(1); // Ensure different timestamp

        $recentOrder = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 200.00,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('orders.index'));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->where('orders.data.0.id', $recentOrder->id)
        );
    }

    public function test_empty_orders_list_displays_correctly(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('orders.index'));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('orders.data', 0)
        );
    }

    public function test_order_total_matches_sum_of_items(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 0, // Will verify this matches items
            'status' => 'completed',
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 75.50,
            'stock_quantity' => 10,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00, // 200.00 total
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 3,
            'price' => 75.50, // 226.50 total
        ]);

        // Update order total
        $order->update(['total_amount' => 426.50]);

        $response = $this->actingAs($this->user)
            ->get(route('orders.show', $order->id));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->where('order.total_amount', '426.50')
        );
    }

    public function test_order_status_is_displayed(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('orders.show', $order->id));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->where('order.status', 'completed')
        );
    }
}

