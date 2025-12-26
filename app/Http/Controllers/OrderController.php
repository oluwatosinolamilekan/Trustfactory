<?php

namespace App\Http\Controllers;

use App\Helpers\PaginationHelper;
use App\Http\Resources\OrderResource;
use App\Repositories\OrderRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private OrderRepository $orderRepository
    ) {}

    /**
     * Display a listing of the user's orders.
     */
    public function index(Request $request): Response
    {
        $orders = $this->orderRepository->getUserOrdersPaginated($request->user()->id, 10);

        return Inertia::render('Orders/Index', [
            'orders' => PaginationHelper::formatForInertia($orders, OrderResource::class),
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, int $id): Response
    {
        $order = $this->orderRepository->findById($id);

        // Ensure the order belongs to the authenticated user
        if (!$order || $order->user_id !== $request->user()->id) {
            abort(404, 'Order not found');
        }

        return Inertia::render('Orders/Show', [
            'order' => (new OrderResource($order))->resolve(),
        ]);
    }
}

