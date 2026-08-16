<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'supplier']);
        
        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('product_code', 'like', "%{$search}%");
            });
        }
        
        // Filter by category
        if ($request->has('category_id') && $request->category_id != '') {
            $query->where('category_id', $request->category_id);
        }
        
        // Filter by supplier
        if ($request->has('supplier_id') && $request->supplier_id != '') {
            $query->where('supplier_id', $request->supplier_id);
        }
        
        // Filter by stock status
        if ($request->has('stock_status')) {
            if ($request->stock_status === 'low') {
                $query->whereRaw('total_stock <= reorder_level');
            } elseif ($request->stock_status === 'out') {
                $query->where('total_stock', 0);
            }
        }
        
        $products = $query->paginate(15);
        $categories = Category::all();
        $suppliers = Supplier::where('is_active', true)->get();
        
        return view('products.index', compact('products', 'categories', 'suppliers'));
    }

    /**
     * Show the form for creating a new product.
     */
    public function create()
    {
        $categories = Category::all();
        $suppliers = Supplier::where('is_active', true)->get();
        
        return view('products.create', compact('categories', 'suppliers'));
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku',
            'product_code' => 'required|string|unique:products,product_code',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'base_price' => 'required|numeric|min:0',
            'reorder_level' => 'required|integer|min:0',
        ]);
        
        $validated['current_price'] = $validated['base_price'];
        $validated['is_active'] = true;
        
        $product = Product::create($validated);
        
        return redirect()->route('products.show', $product)
            ->with('success', 'Product created successfully.');
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        $product->load(['category', 'supplier', 'inventory.warehouse']);
        
        // Get stock by warehouse
        $stockByWarehouse = $product->inventory;
        
        // Recent stock movements
        $recentMovements = $product->stockMovements()
            ->with('warehouse')
            ->latest()
            ->take(10)
            ->get();
        
        // Demand forecast
        $forecast = $product->demandForecasts()
            ->where('forecast_date', '>=', now())
            ->latest()
            ->first();
        
        return view('products.show', compact('product', 'stockByWarehouse', 'recentMovements', 'forecast'));
    }

    /**
     * Show the form for editing the product.
     */
    public function edit(Product $product)
    {
        $categories = Category::all();
        $suppliers = Supplier::where('is_active', true)->get();
        
        return view('products.edit', compact('product', 'categories', 'suppliers'));
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku,' . $product->id,
            'product_code' => 'required|string|unique:products,product_code,' . $product->id,
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'base_price' => 'required|numeric|min:0',
            'reorder_level' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);
        
        $product->update($validated);
        
        // Recalculate dynamic price
        $product->calculateDynamicPrice();
        
        return redirect()->route('products.show', $product)
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product)
    {
        $product->delete();
        
        return redirect()->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }

    /**
     * Recalculate dynamic pricing for a product.
     */
    public function recalculatePrice(Product $product)
    {
        $oldPrice = $product->current_price;
        $newPrice = $product->calculateDynamicPrice();
        
        return back()->with('success', "Price updated from \${$oldPrice} to \${$newPrice}");
    }
}
