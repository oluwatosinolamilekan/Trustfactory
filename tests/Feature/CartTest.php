<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
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
            'price' => 99.99,
            'stock_quantity' => 10,
        ]);
    }

    public function test_guest_cannot_access_cart(): void
    {
        $response = $this->get(route('cart.index'));
        
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_cart(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('cart.index'));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Cart/Index')
                ->has('cartItems')
                ->has('total')
        );
    }

    public function test_user_can_add_product_to_cart(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('cart.add'), [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
    }

    public function test_adding_same_product_increases_quantity(): void
    {
        // Add product first time
        $this->actingAs($this->user)
            ->post(route('cart.add'), [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        // Add same product again
        $this->actingAs($this->user)
            ->post(route('cart.add'), [
                'product_id' => $this->product->id,
                'quantity' => 3,
            ]);
        
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);
    }

    public function test_cannot_add_product_exceeding_stock(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('cart.add'), [
                'product_id' => $this->product->id,
                'quantity' => 15, // Stock is only 10
            ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_cannot_add_out_of_stock_product(): void
    {
        $outOfStockProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('cart.add'), [
                'product_id' => $outOfStockProduct->id,
                'quantity' => 1,
            ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_add_to_cart_requires_product_id(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('cart.add'), [
                'quantity' => 1,
            ]);
        
        $response->assertSessionHasErrors('product_id');
    }

    public function test_add_to_cart_requires_positive_quantity(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('cart.add'), [
                'product_id' => $this->product->id,
                'quantity' => 0,
            ]);
        
        $response->assertSessionHasErrors('quantity');
    }

    public function test_add_to_cart_requires_integer_quantity(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('cart.add'), [
                'product_id' => $this->product->id,
                'quantity' => 'invalid',
            ]);
        
        $response->assertSessionHasErrors('quantity');
    }

    public function test_user_can_update_cart_item_quantity(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('cart.update', $cartItem), [
                'quantity' => 5,
            ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => 5,
        ]);
    }

    public function test_user_cannot_update_another_users_cart_item(): void
    {
        $otherUser = User::factory()->create();
        $cartItem = CartItem::create([
            'user_id' => $otherUser->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('cart.update', $cartItem), [
                'quantity' => 5,
            ]);
        
        $response->assertStatus(403);
    }

    public function test_user_can_remove_item_from_cart(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('cart.remove', $cartItem));
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    public function test_user_cannot_remove_another_users_cart_item(): void
    {
        $otherUser = User::factory()->create();
        $cartItem = CartItem::create([
            'user_id' => $otherUser->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('cart.remove', $cartItem));
        
        $response->assertStatus(403);
    }

    public function test_cart_displays_correct_total(): void
    {
        $product1 = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 50.00,
            'stock_quantity' => 10,
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'price' => 30.00,
            'stock_quantity' => 10,
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $product1->id,
            'quantity' => 2, // 2 * 50 = 100
        ]);

        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
            'quantity' => 3, // 3 * 30 = 90
        ]);

        // Total should be 190.00
        $response = $this->actingAs($this->user)
            ->get(route('cart.index'));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->where('total', 190)
        );
    }

    public function test_empty_cart_shows_no_items(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('cart.index'));
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('cartItems', 0)
                ->where('total', 0)
        );
    }

    public function test_guest_cannot_add_to_cart(): void
    {
        $response = $this->post(route('cart.add'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);
        
        $response->assertRedirect(route('login'));
    }

    public function test_adding_invalid_product_id_fails(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('cart.add'), [
                'product_id' => 99999, // Non-existent product
                'quantity' => 1,
            ]);
        
        $response->assertSessionHasErrors('product_id');
    }
}

