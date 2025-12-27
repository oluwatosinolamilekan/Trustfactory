<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 100.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $this->assertInstanceOf(User::class, $order->user);
        $this->assertEquals($user->id, $order->user->id);
    }

    public function test_order_has_many_order_items(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 100.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 50.00,
        ]);

        $this->assertCount(1, $order->orderItems);
        $this->assertInstanceOf(OrderItem::class, $order->orderItems->first());
    }

    public function test_total_amount_is_cast_to_decimal(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 99.99,
            'status' => OrderStatus::COMPLETED,
        ]);

        $this->assertIsString($order->total_amount);
        $this->assertEquals('99.99', $order->total_amount);
    }

    public function test_for_user_scope_returns_user_orders(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $order1 = Order::create([
            'user_id' => $user1->id,
            'total_amount' => 100.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $order2 = Order::create([
            'user_id' => $user2->id,
            'total_amount' => 200.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $userOrders = Order::forUser($user1->id)->get();

        $this->assertCount(1, $userOrders);
        $this->assertEquals($order1->id, $userOrders->first()->id);
    }

    public function test_for_user_scope_orders_by_created_at_desc(): void
    {
        $user = User::factory()->create();

        // Create older order first
        $oldOrder = Order::create([
            'user_id' => $user->id,
            'total_amount' => 100.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        // Wait a moment to ensure different timestamps
        sleep(1);

        // Create newer order
        $recentOrder = Order::create([
            'user_id' => $user->id,
            'total_amount' => 200.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $orders = Order::forUser($user->id)->get();

        $this->assertEquals($recentOrder->id, $orders->first()->id);
    }

    public function test_by_date_scope_returns_orders_for_specific_date(): void
    {
        $user = User::factory()->create();

        // Create order for today
        $todayOrder = Order::create([
            'user_id' => $user->id,
            'total_amount' => 100.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $today = $todayOrder->created_at->toDateString();

        // Create order for yesterday
        Order::create([
            'user_id' => $user->id,
            'total_amount' => 200.00,
            'status' => OrderStatus::COMPLETED,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $orders = Order::byDate($today)->get();

        // Should find at least the today order
        $this->assertGreaterThanOrEqual(1, $orders->count());
        $this->assertTrue($orders->contains('id', $todayOrder->id));
    }

    public function test_get_orders_by_date_static_method(): void
    {
        $user = User::factory()->create();

        // Create order for today
        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 100.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $today = $order->created_at->toDateString();

        // Create order for yesterday
        Order::create([
            'user_id' => $user->id,
            'total_amount' => 200.00,
            'status' => OrderStatus::COMPLETED,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $orders = Order::getOrdersByDate($today);

        // Should find at least the today order
        $this->assertGreaterThanOrEqual(1, $orders->count());
    }

    public function test_order_can_be_created_with_status(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 150.00,
            'status' => OrderStatus::PENDING,
        ]);

        $this->assertEquals(OrderStatus::PENDING, $order->status);
    }
}

