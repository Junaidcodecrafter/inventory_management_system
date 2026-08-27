<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandForecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'forecast_date',
        'predicted_demand',
        'current_stock',
        'recommended_restock',
        'confidence_score',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'confidence_score' => 'decimal:2',
    ];

    /**
     * Get the product that owns the demand forecast.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate demand forecast using simple moving average.
     * This is a basic algorithm suitable for educational purposes.
     */
    public static function generateForecast($productId, $days = 30)
    {
        $product = Product::findOrFail($productId);
        
        // Get historical sales data for the last 90 days
        $salesHistory = \DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.product_id', $productId)
            ->where('orders.type', 'sale')
            ->where('orders.status', 'completed')
            ->where('orders.order_date', '>=', now()->subDays(90))
            ->selectRaw('DATE(orders.order_date) as sale_date, SUM(order_items.quantity) as total_sold')
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();
        
        if ($salesHistory->isEmpty()) {
            // No historical data, use conservative estimate
            $predictedDemand = $product->reorder_level;
            $confidenceScore = 30.0; // Low confidence
        } else {
            // Calculate simple moving average
            $totalSold = $salesHistory->sum('total_sold');
            $avgDailyDemand = $totalSold / max(1, $salesHistory->count());
            
            // Predict demand for the next period
            $predictedDemand = ceil($avgDailyDemand * $days);
            
            // Calculate confidence score based on data consistency
            $variance = 0;
            foreach ($salesHistory as $record) {
                $variance += pow($record->total_sold - $avgDailyDemand, 2);
            }
            $variance /= max(1, $salesHistory->count());
            $stdDeviation = sqrt($variance);
            
            // Lower standard deviation = higher confidence
            $confidenceScore = max(0, min(100, 100 - ($stdDeviation * 10)));
        }
        
        // Get current total stock
        $currentStock = $product->total_stock;
        
        // Calculate recommended restock quantity
        $recommendedRestock = max(0, $predictedDemand - $currentStock + $product->reorder_level);
        
        // Create or update forecast
        return self::updateOrCreate(
            [
                'product_id' => $productId,
                'forecast_date' => now()->addDays($days)->toDateString(),
            ],
            [
                'predicted_demand' => $predictedDemand,
                'current_stock' => $currentStock,
                'recommended_restock' => $recommendedRestock,
                'confidence_score' => round($confidenceScore, 2),
            ]
        );
    }
}
