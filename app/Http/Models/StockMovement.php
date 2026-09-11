<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'type',
        'quantity',
        'balance_after',
        'reference_type',
        'reference_id',
        'notes',
    ];

    /**
     * Get the product that owns the stock movement.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse that owns the stock movement.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the reference record (polymorphic relation).
     */
    public function reference()
    {
        return $this->morphTo();
    }
}
