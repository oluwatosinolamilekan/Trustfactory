<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Repositories\OrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected OrderRepository $repository;
    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = new OrderRepository();
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
        ]);
    }

    public function test_create_order_creates_new_order(): void
    {
        $order = $this->repository->create($this->user->id, 150.00, 'completed');

        $this->assertInstanceOf(Order::class, $order);
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'total_amount' => 150.00,
            'status' => 'completed',
        ]);
    }

    public function test_create_order_uses_completed_status_by_default(): void
    {
        $order = $this->repository->create($this->user->id, 100.00);

        $this->assertEquals('completed', $order->status);
    }

    public function test_create_order_item_creates_new_order_item(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'completed',
        ]);

        $orderItem = $this->repository->createOrderItem(
            $order->id,
            $this->product->id,
            2,
            50.00
        );

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 50.00,
        ]);
    }

    public function test_get_user_orders_returns_all_user_orders(): void
    {
        $otherUser = User::factory()->create();

        Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'completed',
        ]);

        Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 200.00,
            'status' => 'completed',
        ]);

        Order::create([
            'user_id' => $otherUser->id,
            'total_amount' => 300.00,
            'status' => 'completed',
        ]);

        $orders = $this->repository->getUserOrders($this->user->id);

        $this->assertCount(2, $orders);
    }

    public function test_get_user_orders_sorted_by_created_at_desc(): void
    {
        // Create older order first
        $oldOrder = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'completed',
        ]);

        // Wait a moment to ensure different timestamps
        sleep(1);

        // Create newer order
        $recentOrder = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 200.00,
            'status' => 'completed',
        ]);

        $orders = $this->repository->getUserOrders($this->user->id);

        $this->assertEquals($recentOrder->id, $orders->first()->id);
    }

    public function test_get_user_orders_paginated_returns_paginated_results(): void
    {
        for ($i = 0; $i < 15; $i++) {
            Order::create([
                'user_id' => $this->user->id,
                'total_amount' => 100.00,
                'status' => 'completed',
            ]);
        }

        $paginated = $this->repository->getUserOrdersPaginated($this->user->id, 10);

        $this->assertCount(10, $paginated);
        $this->assertEquals(15, $paginated->total());
    }

    public function test_find_by_id_returns_order_with_items_and_products(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'completed',
        ]);

        $this->repository->createOrderItem(
            $order->id,
            $this->product->id,
            2,
            50.00
        );

        $found = $this->repository->findById($order->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->relationLoaded('orderItems'));
        $this->assertTrue($found->orderItems->first()->relationLoaded('product'));
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $found = $this->repository->findById(999);

        $this->assertNull($found);
    }

    public function test_update_status_changes_order_status(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'pending',
        ]);

        $result = $this->repository->updateStatus($order, 'completed');

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals('completed', $order->status);
    }

    public function test_get_user_orders_loads_order_items_relationship(): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'completed',
        ]);

        $this->repository->createOrderItem(
            $order->id,
            $this->product->id,
            2,
            50.00
        );

        $orders = $this->repository->getUserOrders($this->user->id);

        $this->assertTrue($orders->first()->relationLoaded('orderItems'));
    }
}

