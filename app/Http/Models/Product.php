<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'product_code',
        'description',
        'category_id',
        'supplier_id',
        'base_price',
        'current_price',
        'reorder_level',
        'total_stock',
        'total_sold',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that owns the product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the supplier that owns the product.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the inventory for the product.
     */
    public function inventory()
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Get the order items for the product.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the stock movements for the product.
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get the demand forecasts for the product.
     */
    public function demandForecasts()
    {
        return $this->hasMany(DemandForecast::class);
    }

    /**
     * Calculate dynamic price based on stock and demand.
     */
    public function calculateDynamicPrice()
    {
        // Base price adjustment factors
        $stockLevel = $this->total_stock;
        $demandLevel = $this->total_sold;
        
        // Price increases when stock is low
        if ($stockLevel <= $this->reorder_level) {
            $stockFactor = 1.2; // 20% increase
        } elseif ($stockLevel <= $this->reorder_level * 2) {
            $stockFactor = 1.1; // 10% increase
        } else {
            $stockFactor = 1.0; // No change
        }
        
        // Price increases with high demand
        if ($demandLevel > 100) {
            $demandFactor = 1.15; // 15% increase
        } elseif ($demandLevel > 50) {
            $demandFactor = 1.1; // 10% increase
        } else {
            $demandFactor = 1.0; // No change
        }
        
        $newPrice = $this->base_price * $stockFactor * $demandFactor;
        $this->current_price = round($newPrice, 2);
        $this->save();
        
        return $this->current_price;
    }

    /**
     * Check if product needs restocking.
     */
    public function needsRestocking()
    {
        return $this->total_stock <= $this->reorder_level;
    }
}
