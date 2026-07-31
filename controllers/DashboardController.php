<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index()
    {
        // Get key metrics
        $totalProducts = Product::where('is_active', true)->count();
        $totalCustomers = Customer::where('is_active', true)->count();
        $totalSuppliers = Supplier::where('is_active', true)->count();
        $totalWarehouses = Warehouse::where('is_active', true)->count();
        
        // Recent sales and purchases
        $recentSales = Order::where('type', 'sale')
            ->with(['customer', 'warehouse'])
            ->latest()
            ->take(5)
            ->get();
        
        $recentPurchases = Order::where('type', 'purchase')
            ->with(['supplier', 'warehouse'])
            ->latest()
            ->take(5)
            ->get();
        
        // Products needing restock
        $lowStockProducts = Product::where('is_active', true)
            ->whereRaw('total_stock <= reorder_level')
            ->with('supplier')
            ->take(10)
            ->get();
        
        // Total inventory value
        $totalInventoryValue = \DB::table('inventory')
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->sum(\DB::raw('inventory.quantity * products.current_price'));
        
        // Sales this month
        $salesThisMonth = Order::where('type', 'sale')
            ->where('status', 'completed')
            ->whereMonth('order_date', now()->month)
            ->sum('total_amount');
        
        // Recent stock movements
        $recentMovements = StockMovement::with(['product', 'warehouse'])
            ->latest()
            ->take(10)
            ->get();
        
        return view('dashboard', compact(
            'totalProducts',
            'totalCustomers',
            'totalSuppliers',
            'totalWarehouses',
            'recentSales',
            'recentPurchases',
            'lowStockProducts',
            'totalInventoryValue',
            'salesThisMonth',
            'recentMovements'
        ));
    }
}
