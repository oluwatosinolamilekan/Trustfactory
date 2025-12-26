<?php

namespace App\Repositories;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class CartRepository
{
    /**
     * Get all cart items for a user with product relationship
     */
    public function getUserCartItems(int $userId): Collection
    {
        return CartItem::where('user_id', $userId)
            ->with('product')
            ->get();
    }

    /**
     * Find a cart item by user and product
     */
    public function findByUserAndProduct(int $userId, int $productId): ?CartItem
    {
        return CartItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();
    }

    /**
     * Create a new cart item
     */
    public function create(int $userId, int $productId, int $quantity): CartItem
    {
        return CartItem::create([
            'user_id' => $userId,
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Update cart item quantity
     */
    public function updateQuantity(CartItem $cartItem, int $quantity): bool
    {
        return $cartItem->update(['quantity' => $quantity]);
    }

    /**
     * Delete a cart item
     */
    public function delete(CartItem $cartItem): bool
    {
        return $cartItem->delete();
    }

    /**
     * Delete all cart items for a user
     */
    public function clearUserCart(int $userId): int
    {
        return CartItem::where('user_id', $userId)->delete();
    }

    /**
     * Calculate total amount for cart items
     */
    public function calculateTotal(Collection $cartItems): float
    {
        return $cartItems->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });
    }

    /**
     * Check if user owns the cart item
     */
    public function userOwnsCartItem(CartItem $cartItem, int $userId): bool
    {
        return $cartItem->user_id === $userId;
    }

    /**
     * Add product to cart or update quantity if exists
     */
    public function addOrUpdateProduct(int $userId, int $productId, int $quantity, Product $product): array
    {
        $cartItem = $this->findByUserAndProduct($userId, $productId);

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $quantity;
            
            if ($newQuantity > $product->stock_quantity) {
                $cartItem->quantity = $product->stock_quantity;
                $cartItem->save();
                
                return [
                    'status' => 'warning',
                    'message' => 'Quantity adjusted to available stock',
                    'cartItem' => $cartItem
                ];
            }
            
            $cartItem->quantity = $newQuantity;
            $cartItem->save();

            return [
                'status' => 'success',
                'message' => 'Product quantity updated in cart',
                'cartItem' => $cartItem
            ];
        }

        $cartItem = $this->create($userId, $productId, $quantity);

        return [
            'status' => 'success',
            'message' => 'Product added to cart',
            'cartItem' => $cartItem
        ];
    }
}

