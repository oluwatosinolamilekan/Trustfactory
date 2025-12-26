<?php

namespace App\Services;

use Exception;
use App\Jobs\LowStockNotification;
use App\Traits\ValidatesStock;
use Illuminate\Support\Facades\DB;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;

class CheckoutService
{
    use ValidatesStock;

    public function __construct(
        protected CartRepository $cartRepository,
        protected OrderRepository $orderRepository,
        protected ProductRepository $productRepository
    ) {}

    /**
     * Process checkout for a user
     * 
     * @param int $userId
     * @return array
     * @throws \Exception
     */
    public function processCheckout(int $userId): array
    {
        $cartItems = $this->cartRepository->getUserCartItems($userId);

        if ($cartItems->isEmpty()) {
            throw new Exception('Your cart is empty');
        }

        $result = DB::transaction(function () use ($cartItems, $userId) {
            // Validate stock and calculate total
            $total = $this->validateStockAndCalculateTotal($cartItems);

            // Create order
            $order = $this->orderRepository->create($userId, $total, 'completed');

            // Process each cart item
            foreach ($cartItems as $cartItem) {
                // Create order item
                $this->orderRepository->createOrderItem(
                    $order->id,
                    $cartItem->product_id,
                    $cartItem->quantity,
                    $cartItem->product->price
                );

                // Update product stock
                $product = $cartItem->product;
                $this->productRepository->decreaseStock($product, $cartItem->quantity);

                // Check for low stock and dispatch notification
                if ($this->productRepository->isLowStock($product)) {
                    LowStockNotification::dispatch($product);
                }

                // Remove item from cart
                $this->cartRepository->delete($cartItem);
            }

            return [
                'order' => $order,
                'total' => $total,
            ];
        });

        return $result;
    }

    /**
     * Validate stock availability and calculate total
     * 
     * @param \Illuminate\Database\Eloquent\Collection $cartItems
     * @return float
     * @throws \Exception
     */
    protected function validateStockAndCalculateTotal($cartItems): float
    {
        $total = 0;

        foreach ($cartItems as $cartItem) {
            $this->validateStockOrFail($cartItem->product, $cartItem->quantity, $this->productRepository);
            $total += $cartItem->quantity * $cartItem->product->price;
        }

        return $total;
    }

    /**
     * Validate cart before checkout
     * 
     * @param int $userId
     * @return array
     */
    public function validateCart(int $userId): array
    {
        $cartItems = $this->cartRepository->getUserCartItems($userId);
        $errors = [];

        foreach ($cartItems as $cartItem) {
            if (!$this->productRepository->hasSufficientStock($cartItem->product, $cartItem->quantity)) {
                $errors[] = [
                    'product' => $cartItem->product->name,
                    'requested' => $cartItem->quantity,
                    'available' => $cartItem->product->stock_quantity,
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}

