<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository
{
    /**
     * Create a new order
     */
    public function create(int $userId, float $totalAmount, string $status = 'completed'): Order
    {
        return Order::create([
            'user_id' => $userId,
            'total_amount' => $totalAmount,
            'status' => $status,
        ]);
    }

    /**
     * Create an order item
     */
    public function createOrderItem(int $orderId, int $productId, int $quantity, float $price): OrderItem
    {
        return OrderItem::create([
            'order_id' => $orderId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'price' => $price,
        ]);
    }

    /**
     * Get all orders for a user
     */
    public function getUserOrders(int $userId): Collection
    {
        return Order::forUser($userId)->get();
    }

    /**
     * Get paginated orders for a user
     */
    public function getUserOrdersPaginated(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        return Order::forUser($userId)->paginate($perPage);
    }

    /**
     * Find an order by ID
     */
    public function findById(int $orderId): ?Order
    {
        return Order::with('orderItems.product')->find($orderId);
    }

    /**
     * Update order status
     */
    public function updateStatus(Order $order, string $status): bool
    {
        return $order->update(['status' => $status]);
    }
}

