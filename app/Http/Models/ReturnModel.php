<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnModel extends Model
{
    use HasFactory;

    protected $table = 'returns';

    protected $fillable = [
        'return_number',
        'order_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'reason',
        'status',
        'refund_amount',
        'return_date',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'return_date' => 'date',
    ];

    /**
     * Get the order that owns the return.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product that owns the return.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse that owns the return.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Generate unique return number.
     */
    public static function generateReturnNumber()
    {
        $date = date('Ymd');
        $lastReturn = self::whereDate('created_at', today())
            ->latest()
            ->first();
        
        $sequence = $lastReturn ? intval(substr($lastReturn->return_number, -4)) + 1 : 1;
        
        return 'RET' . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Approve return and refund customer.
     */
    public function approve()
    {
        if ($this->status === 'approved') {
            return;
        }
        
        \DB::transaction(function () {
            // Add returned items back to inventory
            $inventory = Inventory::firstOrCreate(
                [
                    'product_id' => $this->product_id,
                    'warehouse_id' => $this->warehouse_id,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'available_quantity' => 0,
                ]
            );
            
            $inventory->addStock($this->quantity, 'Return', $this->id, 'Product returned: ' . $this->reason);
            
            $this->status = 'approved';
            $this->save();
        });
    }

    /**
     * Process refund for approved return.
     */
    public function processRefund()
    {
        if ($this->status !== 'approved') {
            throw new \Exception('Return must be approved before processing refund');
        }
        
        $this->status = 'refunded';
        $this->save();
        
        // Here you would integrate with payment gateway for actual refund
        // For this project, we just update the status
    }
}
