<?php

namespace Tests\Feature;

use App\Jobs\LowStockNotification;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckoutTest extends TestCase
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

    public function test_guest_cannot_checkout(): void
    {
        $response = $this->post(route('cart.checkout'));
        
        $response->assertRedirect(route('login'));
    }

    public function test_user_can_checkout_with_items_in_cart(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('cart.checkout'));
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        // Check order was created
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'total_amount' => 200.00,
            'status' => 'completed',
        ]);
        
        // Check order items were created
        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);
        
        // Check cart was emptied
        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_checkout_updates_product_stock(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        $initialStock = $this->product->stock_quantity;

        $this->actingAs($this->user)
            ->post(route('cart.checkout'));
        
        $this->product->refresh();
        $this->assertEquals($initialStock - 5, $this->product->stock_quantity);
    }

    public function test_checkout_with_empty_cart_fails(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('cart.checkout'));
        
        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        $this->assertDatabaseMissing('orders', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_checkout_fails_when_product_out_of_stock(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        // Update product to be out of stock
        $this->product->update(['stock_quantity' => 0]);

        $response = $this->actingAs($this->user)
            ->post(route('cart.checkout'));
        
        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        // Ensure order was not created
        $this->assertDatabaseMissing('orders', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_checkout_fails_when_insufficient_stock(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 25, // Product has only 20 in stock
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('cart.checkout'));
        
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_checkout_with_multiple_products(): void
    {
        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 50.00,
            'stock_quantity' => 10,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2, // 2 * 100 = 200
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 3, // 3 * 50 = 150
        ]);

        $this->actingAs($this->user)
            ->post(route('cart.checkout'));
        
        // Check order total is correct
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'total_amount' => 350.00,
        ]);
        
        // Check both products were added to order
        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertEquals(2, $order->orderItems()->count());
    }

    public function test_checkout_dispatches_low_stock_notification(): void
    {
        Queue::fake();

        // Create product with low stock
        $lowStockProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 50.00,
            'stock_quantity' => 12, // Will become 2 after checkout
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $lowStockProduct->id,
            'quantity' => 10,
        ]);

        $this->actingAs($this->user)
            ->post(route('cart.checkout'));
        
        Queue::assertPushed(LowStockNotification::class);
    }

    public function test_checkout_does_not_dispatch_notification_for_adequate_stock(): void
    {
        Queue::fake();

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2, // Stock will be 18 after checkout
        ]);

        $this->actingAs($this->user)
            ->post(route('cart.checkout'));
        
        Queue::assertNotPushed(LowStockNotification::class);
    }

    public function test_checkout_is_atomic_transaction(): void
    {
        // Create two products, one with insufficient stock
        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 50.00,
            'stock_quantity' => 5,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 10, // Exceeds stock
        ]);

        $initialStock = $this->product->stock_quantity;

        $response = $this->actingAs($this->user)
            ->post(route('cart.checkout'));
        
        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        // Ensure no order was created
        $this->assertDatabaseMissing('orders', [
            'user_id' => $this->user->id,
        ]);
        
        // Ensure first product stock was not updated (transaction rolled back)
        $this->product->refresh();
        $this->assertEquals($initialStock, $this->product->stock_quantity);
        
        // Ensure cart items were not removed
        $this->assertEquals(2, $this->user->cartItems()->count());
    }

    public function test_successful_checkout_redirects_to_order_page(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('cart.checkout'));
        
        $order = Order::where('user_id', $this->user->id)->first();
        
        $response->assertRedirect(route('orders.show', $order->id));
    }

    public function test_checkout_calculates_correct_total_with_decimals(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 19.99,
            'stock_quantity' => 10,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 3, // 3 * 19.99 = 59.97
        ]);

        $this->actingAs($this->user)
            ->post(route('cart.checkout'));
        
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'total_amount' => 59.97,
        ]);
    }
}

