<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Repositories\CartRepository;
use App\Repositories\ProductRepository;
use App\Services\CheckoutService;
use App\Traits\ValidatesStock;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    use ValidatesStock;

    public function __construct(
        protected CartRepository $cartRepository,
        protected ProductRepository $productRepository,
        protected CheckoutService $checkoutService
    ) {}

    public function index(): Response
    {
        $cartItems = $this->cartRepository->getUserCartItems(auth()->id());
        $total = $this->cartRepository->calculateTotal($cartItems);
        
        return Inertia::render('Cart/Index', [
            'cartItems' => CartItemResource::collection($cartItems)->resolve(),
            'total' => $total,
        ]);
    }

    public function add(AddToCartRequest $request): RedirectResponse
    {
        $product = $this->productRepository->findByIdOrFail($request->product_id);

        if ($error = $this->validateStock($product, $request->quantity, $this->productRepository)) {
            return $error;
        }

        $result = $this->cartRepository->addOrUpdateProduct(
            auth()->id(),
            $request->product_id,
            $request->quantity,
            $product
        );

        return back()->with($result['status'], $result['message']);
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): RedirectResponse
    {
        $this->authorize('update', $cartItem);

        if ($error = $this->validateStock($cartItem->product, $request->quantity, $this->productRepository)) {
            return $error;
        }

        $this->cartRepository->updateQuantity($cartItem, $request->quantity);

        return back()->with('success', 'Cart updated successfully');
    }

    public function remove(CartItem $cartItem): RedirectResponse
    {
        $this->authorize('delete', $cartItem);

        $this->cartRepository->delete($cartItem);

        return back()->with('success', 'Item removed from cart');
    }

    public function checkout(): RedirectResponse
    {
        try {
            $result = $this->checkoutService->processCheckout(auth()->id());
            return redirect()->route('orders.show', $result['order']->id)->with('success', 'Order placed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
