<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\CartItem;
use App\Repositories\CartRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected CartRepository $repository;
    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = new CartRepository();
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 50.00,
            'stock_quantity' => 10,
        ]);
    }

    public function test_get_user_cart_items_returns_users_items(): void
    {
        $otherUser = User::factory()->create();
        
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        CartItem::create([
            'user_id' => $otherUser->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
        ]);

        $items = $this->repository->getUserCartItems($this->user->id);

        $this->assertCount(1, $items);
        $this->assertEquals($this->user->id, $items->first()->user_id);
    }

    public function test_get_user_cart_items_loads_product_relationship(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $items = $this->repository->getUserCartItems($this->user->id);

        $this->assertTrue($items->first()->relationLoaded('product'));
        $this->assertInstanceOf(Product::class, $items->first()->product);
    }

    public function test_find_by_user_and_product_returns_cart_item(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $found = $this->repository->findByUserAndProduct($this->user->id, $this->product->id);

        $this->assertNotNull($found);
        $this->assertEquals($cartItem->id, $found->id);
    }

    public function test_find_by_user_and_product_returns_null_when_not_found(): void
    {
        $found = $this->repository->findByUserAndProduct($this->user->id, 999);

        $this->assertNull($found);
    }

    public function test_create_adds_new_cart_item(): void
    {
        $cartItem = $this->repository->create($this->user->id, $this->product->id, 3);

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
        ]);
    }

    public function test_update_quantity_changes_cart_item_quantity(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $result = $this->repository->updateQuantity($cartItem, 5);

        $this->assertTrue($result);
        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => 5,
        ]);
    }

    public function test_delete_removes_cart_item(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $result = $this->repository->delete($cartItem);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    public function test_clear_user_cart_removes_all_items(): void
    {
        $product2 = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 3,
        ]);

        $count = $this->repository->clearUserCart($this->user->id);

        $this->assertEquals(2, $count);
        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_calculate_total_computes_correct_amount(): void
    {
        $product2 = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 30.00,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2, // 2 * 50 = 100
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 3, // 3 * 30 = 90
        ]);

        $items = $this->repository->getUserCartItems($this->user->id);
        $total = $this->repository->calculateTotal($items);

        $this->assertEquals(190.00, $total);
    }

    public function test_user_owns_cart_item_returns_true_for_owner(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $result = $this->repository->userOwnsCartItem($cartItem, $this->user->id);

        $this->assertTrue($result);
    }

    public function test_user_owns_cart_item_returns_false_for_non_owner(): void
    {
        $otherUser = User::factory()->create();
        
        $cartItem = CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $result = $this->repository->userOwnsCartItem($cartItem, $otherUser->id);

        $this->assertFalse($result);
    }

    public function test_add_or_update_product_creates_new_item_when_not_exists(): void
    {
        $result = $this->repository->addOrUpdateProduct(
            $this->user->id,
            $this->product->id,
            3,
            $this->product
        );

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Product added to cart', $result['message']);
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
        ]);
    }

    public function test_add_or_update_product_updates_quantity_when_exists(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $result = $this->repository->addOrUpdateProduct(
            $this->user->id,
            $this->product->id,
            3,
            $this->product
        );

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Product quantity updated in cart', $result['message']);
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);
    }

    public function test_add_or_update_product_adjusts_to_available_stock(): void
    {
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 8,
        ]);

        $result = $this->repository->addOrUpdateProduct(
            $this->user->id,
            $this->product->id,
            5, // 8 + 5 = 13, but stock is only 10
            $this->product
        );

        $this->assertEquals('warning', $result['status']);
        $this->assertEquals('Quantity adjusted to available stock', $result['message']);
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);
    }
}

