<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * Display a listing of inventory.
     */
    public function index(Request $request)
    {
        $query = Inventory::with(['product', 'warehouse']);
        
        // Filter by warehouse
        if ($request->has('warehouse_id') && $request->warehouse_id != '') {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        
        // Filter by product
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }
        
        // Filter by stock status
        if ($request->has('stock_status')) {
            if ($request->stock_status === 'low') {
                $query->whereHas('product', function($q) {
                    $q->whereRaw('inventory.quantity <= products.reorder_level');
                });
            } elseif ($request->stock_status === 'out') {
                $query->where('quantity', 0);
            }
        }
        
        $inventory = $query->paginate(20);
        $warehouses = Warehouse::where('is_active', true)->get();
        
        return view('inventory.index', compact('inventory', 'warehouses'));
    }

    /**
     * Show the form for adjusting stock.
     */
    public function adjust()
    {
        $products = Product::where('is_active', true)->get();
        $warehouses = Warehouse::where('is_active', true)->get();
        
        return view('inventory.adjust', compact('products', 'warehouses'));
    }

    /**
     * Process stock adjustment.
     */
    public function processAdjustment(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'adjustment_type' => 'required|in:add,remove',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);
        
        \DB::transaction(function () use ($validated) {
            $inventory = Inventory::firstOrCreate(
                [
                    'product_id' => $validated['product_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'available_quantity' => 0,
                ]
            );
            
            if ($validated['adjustment_type'] === 'add') {
                $inventory->addStock($validated['quantity'], null, null, $validated['notes'] ?? 'Manual adjustment');
            } else {
                $inventory->removeStock($validated['quantity'], null, null, $validated['notes'] ?? 'Manual adjustment');
            }
        });
        
        return redirect()->route('inventory.index')
            ->with('success', 'Stock adjusted successfully.');
    }

    /**
     * Display stock movements.
     */
    public function movements(Request $request)
    {
        $query = StockMovement::with(['product', 'warehouse']);
        
        // Filter by warehouse
        if ($request->has('warehouse_id') && $request->warehouse_id != '') {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        
        // Filter by product
        if ($request->has('product_id') && $request->product_id != '') {
            $query->where('product_id', $request->product_id);
        }
        
        // Filter by type
        if ($request->has('type') && $request->type != '') {
            $query->where('type', $request->type);
        }
        
        // Date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        $movements = $query->latest()->paginate(20);
        $products = Product::where('is_active', true)->get();
        $warehouses = Warehouse::where('is_active', true)->get();
        
        return view('inventory.movements', compact('movements', 'products', 'warehouses'));
    }
}
