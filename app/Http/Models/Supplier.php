<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'performance_rating',
        'total_orders',
        'on_time_deliveries',
        'is_active',
    ];

    protected $casts = [
        'performance_rating' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the products supplied by this supplier.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the purchase orders for this supplier.
     */
    public function orders()
    {
        return $this->hasMany(Order::class)->where('type', 'purchase');
    }

    /**
     * Update supplier performance rating.
     */
    public function updatePerformanceRating()
    {
        if ($this->total_orders > 0) {
            $this->performance_rating = ($this->on_time_deliveries / $this->total_orders) * 5;
            $this->save();
        }
    }

    /**
     * Record an order completion.
     */
    public function recordOrderCompletion($onTime = true)
    {
        $this->total_orders++;
        if ($onTime) {
            $this->on_time_deliveries++;
        }
        $this->updatePerformanceRating();
    }
}
