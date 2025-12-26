<?php

namespace App\Traits;

use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Http\RedirectResponse;

trait ValidatesStock
{
    /**
     * Validate if a product has sufficient stock.
     * Returns a redirect response if validation fails, null otherwise.
     */
    protected function validateStock(Product $product, int $requestedQuantity, ?ProductRepository $productRepository = null): ?RedirectResponse
    {
        $repository = $productRepository ?? app(ProductRepository::class);
        
        if (!$repository->hasSufficientStock($product, $requestedQuantity)) {
            return back()->with('error', 'Insufficient stock available');
        }
        
        return null;
    }
    
    /**
     * Validate if a product has sufficient stock and throw exception if not.
     */
    protected function validateStockOrFail(Product $product, int $requestedQuantity, ?ProductRepository $productRepository = null): void
    {
        $repository = $productRepository ?? app(ProductRepository::class);
        
        if (!$repository->hasSufficientStock($product, $requestedQuantity)) {
            throw new \Exception("Insufficient stock for {$product->name}");
        }
    }
}

