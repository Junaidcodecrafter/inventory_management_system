<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'type',
        'customer_id',
        'supplier_id',
        'warehouse_id',
        'status',
        'total_amount',
        'order_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
    ];

    /**
     * Get the customer that owns the order.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the supplier that owns the order.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the warehouse that owns the order.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the order items for the order.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the returns for the order.
     */
    public function returns()
    {
        return $this->hasMany(ReturnModel::class);
    }

    /**
     * Generate unique order number.
     */
    public static function generateOrderNumber($type)
    {
        $prefix = $type === 'sale' ? 'SO' : 'PO';
        $date = date('Ymd');
        $lastOrder = self::where('type', $type)
            ->whereDate('created_at', today())
            ->latest()
            ->first();
        
        $sequence = $lastOrder ? intval(substr($lastOrder->order_number, -4)) + 1 : 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Complete the order and update inventory.
     */
    public function complete()
    {
        if ($this->status === 'completed') {
            return;
        }
        
        \DB::transaction(function () {
            foreach ($this->orderItems as $item) {
                $inventory = Inventory::firstOrCreate(
                    [
                        'product_id' => $item->product_id,
                        'warehouse_id' => $this->warehouse_id,
                    ],
                    [
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'available_quantity' => 0,
                    ]
                );
                
                if ($this->type === 'purchase') {
                    // Incoming stock from supplier
                    $inventory->addStock($item->quantity, 'Order', $this->id, 'Purchase order completed');
                } else {
                    // Outgoing stock to customer
                    $inventory->releaseReservedStock($item->quantity);
                    $inventory->removeStock($item->quantity, 'Order', $this->id, 'Sales order completed');
                    
                    // Update product total sold
                    $item->product->total_sold += $item->quantity;
                    $item->product->save();
                    
                    // Update customer total purchases
                    if ($this->customer) {
                        $this->customer->addPurchase($this->total_amount);
                    }
                }
                
                // Recalculate dynamic price
                $item->product->calculateDynamicPrice();
            }
            
            $this->status = 'completed';
            $this->actual_delivery_date = now();
            $this->save();
            
            // Update supplier performance for purchase orders
            if ($this->type === 'purchase' && $this->supplier) {
                $onTime = $this->actual_delivery_date <= $this->expected_delivery_date;
                $this->supplier->recordOrderCompletion($onTime);
            }
        });
    }
}
