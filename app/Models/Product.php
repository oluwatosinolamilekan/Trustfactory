<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'category_id',
        'description',
        'price',
        'stock_quantity',
        'image_url',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isLowStock(int $threshold = 10): bool
    {
        return $this->stock_quantity <= $threshold && $this->stock_quantity > 0;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock_quantity <= 0;
    }

    /**
     * Scope a query to apply all product filters.
     */
    public function scopeApplyFilters(
        Builder $query,
        ?string $search = null,
        ?string $categorySlug = null,
        string|float|null $minPrice = null,
        string|float|null $maxPrice = null
    ): Builder {
        // Apply search filter
        $query = match (empty($search)) {
            true => $query,
            false => $query->where('name', 'like', '%' . $search . '%'),
        };

        // Apply category filter
        $query = match (empty($categorySlug)) {
            true => $query,
            false => $query->whereHas('category', fn($q) => $q->where('slug', $categorySlug)),
        };

        // Apply minimum price filter
        $query = match (empty($minPrice)) {
            true => $query,
            false => $query->where('price', '>=', $minPrice),
        };

        // Apply maximum price filter
        $query = match (empty($maxPrice)) {
            true => $query,
            false => $query->where('price', '<=', $maxPrice),
        };

        return $query;
    }
}
