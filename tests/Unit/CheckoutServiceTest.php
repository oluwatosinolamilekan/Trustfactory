<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\CartItem;
use App\Services\CheckoutService;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CheckoutService $service;
    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new CheckoutService(
            new CartRepository(),
            new OrderRepository(),
            new ProductRepository()
        );
        
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 100.00,
            'stock_quantity' => 20,
        ]);
    }

    public function test_process_checkout_creates_order(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $result = $this->service->processCheckout($this->user->id);

        $this->assertArrayHasKey('order', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(200.00, $result['total']);
    }

    public function test_process_checkout_creates_order_items(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $result = $this->service->processCheckout($this->user->id);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $result['order']->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);
    }

    public function test_process_checkout_decreases_product_stock(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        $initialStock = $this->product->stock_quantity;

        $this->service->processCheckout($this->user->id);

        $this->product->refresh();
        $this->assertEquals($initialStock - 5, $this->product->stock_quantity);
    }

    public function test_process_checkout_clears_cart(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $this->service->processCheckout($this->user->id);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_process_checkout_throws_exception_for_empty_cart(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Your cart is empty');

        $this->service->processCheckout($this->user->id);
    }

    public function test_process_checkout_throws_exception_for_insufficient_stock(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 25, // Stock is only 20
        ]);

        $this->expectException(\Exception::class);

        $this->service->processCheckout($this->user->id);
    }

    public function test_process_checkout_is_transactional(): void
    {
        $product2 = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
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

        try {
            $this->service->processCheckout($this->user->id);
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Verify rollback - stock should not change
        $this->product->refresh();
        $this->assertEquals($initialStock, $this->product->stock_quantity);

        // Verify no order was created
        $this->assertDatabaseMissing('orders', [
            'user_id' => $this->user->id,
        ]);

        // Verify cart items still exist
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_process_checkout_handles_multiple_products(): void
    {
        $product2 = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 50.00,
            'stock_quantity' => 10,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2, // 200.00
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 3, // 150.00
        ]);

        $result = $this->service->processCheckout($this->user->id);

        $this->assertEquals(350.00, $result['total']);
        $this->assertEquals(2, $result['order']->orderItems()->count());
    }

    public function test_validate_cart_returns_valid_for_good_cart(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        $result = $this->service->validateCart($this->user->id);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_cart_returns_errors_for_insufficient_stock(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 25, // Stock is only 20
        ]);

        $result = $this->service->validateCart($this->user->id);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertEquals($this->product->name, $result['errors'][0]['product']);
        $this->assertEquals(25, $result['errors'][0]['requested']);
        $this->assertEquals(20, $result['errors'][0]['available']);
    }

    public function test_validate_cart_checks_all_items(): void
    {
        $product2 = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 50.00,
            'stock_quantity' => 5,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 25, // Exceeds stock
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 10, // Exceeds stock
        ]);

        $result = $this->service->validateCart($this->user->id);

        $this->assertFalse($result['valid']);
        $this->assertCount(2, $result['errors']);
    }

    public function test_checkout_calculates_total_correctly(): void
    {
        $product2 = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 19.99,
            'stock_quantity' => 10,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2, // 200.00
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 3, // 59.97
        ]);

        $result = $this->service->processCheckout($this->user->id);

        $this->assertEquals(259.97, $result['total']);
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'total_amount' => 259.97,
        ]);
    }
}

