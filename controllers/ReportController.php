<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\StockMovement;
use App\Models\DemandForecast;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Display reports dashboard.
     */
    public function index()
    {
        return view('reports.index');
    }

    /**
     * Generate sales report.
     */
    public function sales(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->subMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));
        
        $salesOrders = Order::where('type', 'sale')
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->with(['customer', 'warehouse', 'orderItems.product'])
            ->get();
        
        // Calculate summary statistics
        $totalRevenue = $salesOrders->where('status', 'completed')->sum('total_amount');
        $totalOrders = $salesOrders->count();
        $completedOrders = $salesOrders->where('status', 'completed')->count();
        $pendingOrders = $salesOrders->where('status', 'pending')->count();
        
        // Top selling products
        $topProducts = \DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.type', 'sale')
            ->where('orders.status', 'completed')
            ->whereBetween('orders.order_date', [$dateFrom, $dateTo])
            ->select('products.name', \DB::raw('SUM(order_items.quantity) as total_sold'), \DB::raw('SUM(order_items.total_price) as total_revenue'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get();
        
        return view('reports.sales', compact(
            'salesOrders',
            'totalRevenue',
            'totalOrders',
            'completedOrders',
            'pendingOrders',
            'topProducts',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Generate stock movement report.
     */
    public function stockMovements(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->subMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));
        $productId = $request->get('product_id');
        
        $query = StockMovement::with(['product', 'warehouse'])
            ->whereBetween('created_at', [$dateFrom, $dateTo]);
        
        if ($productId) {
            $query->where('product_id', $productId);
        }
        
        $movements = $query->latest()->paginate(50);
        
        // Summary by type
        $summary = StockMovement::whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($productId, function($q) use ($productId) {
                $q->where('product_id', $productId);
            })
            ->select('type', \DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('type')
            ->get();
        
        $products = Product::where('is_active', true)->get();
        
        return view('reports.stock-movements', compact(
            'movements',
            'summary',
            'products',
            'dateFrom',
            'dateTo',
            'productId'
        ));
    }

    /**
     * Generate supplier performance report.
     */
    public function suppliers()
    {
        $suppliers = Supplier::with('products')
            ->orderByDesc('performance_rating')
            ->get();
        
        // Get order statistics for each supplier
        foreach ($suppliers as $supplier) {
            $supplier->pending_orders = $supplier->orders()->where('status', 'pending')->count();
            $supplier->completed_orders = $supplier->orders()->where('status', 'completed')->count();
            $supplier->total_value = $supplier->orders()->where('status', 'completed')->sum('total_amount');
        }
        
        return view('reports.suppliers', compact('suppliers'));
    }

    /**
     * Generate low stock alert report.
     */
    public function lowStock()
    {
        $products = Product::where('is_active', true)
            ->whereRaw('total_stock <= reorder_level')
            ->with(['supplier', 'inventory.warehouse'])
            ->orderBy('total_stock')
            ->get();
        
        return view('reports.low-stock', compact('products'));
    }

    /**
     * Generate demand forecast report.
     */
    public function demandForecast()
    {
        $forecasts = DemandForecast::with('product')
            ->where('forecast_date', '>=', now())
            ->orderBy('forecast_date')
            ->orderByDesc('recommended_restock')
            ->get();
        
        return view('reports.demand-forecast', compact('forecasts'));
    }

    /**
     * Generate demand forecasts for all products.
     */
    public function generateForecasts()
    {
        $products = Product::where('is_active', true)->get();
        
        foreach ($products as $product) {
            DemandForecast::generateForecast($product->id, 30);
        }
        
        return back()->with('success', 'Demand forecasts generated for all products.');
    }
}
