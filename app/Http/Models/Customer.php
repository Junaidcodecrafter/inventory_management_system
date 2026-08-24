<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'total_purchases',
        'is_active',
    ];

    protected $casts = [
        'total_purchases' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the sales orders for this customer.
     */
    public function orders()
    {
        return $this->hasMany(Order::class)->where('type', 'sale');
    }

    /**
     * Add to customer's total purchases.
     */
    public function addPurchase($amount)
    {
        $this->total_purchases += $amount;
        $this->save();
    }
}
