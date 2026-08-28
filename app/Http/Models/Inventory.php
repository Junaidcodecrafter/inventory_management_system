<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity',
        'available_quantity',
    ];

    /**
     * Get the product that owns the inventory.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse that owns the inventory.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Update available quantity.
     */
    public function updateAvailableQuantity()
    {
        $this->available_quantity = $this->quantity - $this->reserved_quantity;
        $this->save();
    }

    /**
     * Add stock to inventory.
     */
    public function addStock($quantity, $reference_type = null, $reference_id = null, $notes = null)
    {
        $this->quantity += $quantity;
        $this->updateAvailableQuantity();
        
        // Record stock movement
        StockMovement::create([
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'type' => 'in',
            'quantity' => $quantity,
            'balance_after' => $this->quantity,
            'reference_type' => $reference_type,
            'reference_id' => $reference_id,
            'notes' => $notes,
        ]);
        
        // Update product total stock
        $this->product->total_stock += $quantity;
        $this->product->save();
    }

    /**
     * Remove stock from inventory.
     */
    public function removeStock($quantity, $reference_type = null, $reference_id = null, $notes = null)
    {
        if ($this->available_quantity < $quantity) {
            throw new \Exception('Insufficient stock available');
        }
        
        $this->quantity -= $quantity;
        $this->updateAvailableQuantity();
        
        // Record stock movement
        StockMovement::create([
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'type' => 'out',
            'quantity' => $quantity,
            'balance_after' => $this->quantity,
            'reference_type' => $reference_type,
            'reference_id' => $reference_id,
            'notes' => $notes,
        ]);
        
        // Update product total stock
        $this->product->total_stock -= $quantity;
        $this->product->save();
    }

    /**
     * Reserve stock for an order.
     */
    public function reserveStock($quantity)
    {
        if ($this->available_quantity < $quantity) {
            throw new \Exception('Insufficient stock available to reserve');
        }
        
        $this->reserved_quantity += $quantity;
        $this->updateAvailableQuantity();
    }

    /**
     * Release reserved stock.
     */
    public function releaseReservedStock($quantity)
    {
        $this->reserved_quantity -= $quantity;
        if ($this->reserved_quantity < 0) {
            $this->reserved_quantity = 0;
        }
        $this->updateAvailableQuantity();
    }
}
