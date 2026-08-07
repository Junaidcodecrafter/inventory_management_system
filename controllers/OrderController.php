<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Inventory;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index(Request $request)
    {
        $query = Order::with(['customer', 'supplier', 'warehouse']);
        
        // Filter by type
        if ($request->has('type') && $request->type != '') {
            $query->where('type', $request->type);
        }
        
        // Filter by status
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }
        
        // Search by order number
        if ($request->has('search')) {
            $query->where('order_number', 'like', "%{$request->search}%");
        }
        
        $orders = $query->latest()->paginate(15);
        
        return view('orders.index', compact('orders'));
    }

    /**
     * Show the form for creating a new order.
     */
    public function create(Request $request)
    {
        $type = $request->get('type', 'sale');
        $products = Product::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $warehouses = Warehouse::where('is_active', true)->get();
        
        return view('orders.create', compact('type', 'products', 'customers', 'suppliers', 'warehouses'));
    }

    /**
     * Store a newly created order.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:sale,purchase',
            'customer_id' => 'required_if:type,sale|nullable|exists:customers,id',
            'supplier_id' => 'required_if:type,purchase|nullable|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        
        \DB::transaction(function () use ($validated) {
            // Generate order number
            $orderNumber = Order::generateOrderNumber($validated['type']);
            
            // Calculate total amount
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                $totalAmount += $product->current_price * $item['quantity'];
            }
            
            // Create order
            $order = Order::create([
                'order_number' => $orderNumber,
                'type' => $validated['type'],
                'customer_id' => $validated['customer_id'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'],
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'order_date' => $validated['order_date'],
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
            
            // Create order items
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->current_price,
                    'total_price' => $product->current_price * $item['quantity'],
                ]);
                
                // Reserve stock for sales orders
                if ($validated['type'] === 'sale') {
                    $inventory = Inventory::firstOrCreate(
                        [
                            'product_id' => $item['product_id'],
                            'warehouse_id' => $validated['warehouse_id'],
                        ],
                        [
                            'quantity' => 0,
                            'reserved_quantity' => 0,
                            'available_quantity' => 0,
                        ]
                    );
                    
                    $inventory->reserveStock($item['quantity']);
                }
            }
            
            session()->flash('success', 'Order created successfully.');
            session()->flash('order_id', $order->id);
        });
        
        $orderId = session('order_id');
        return redirect()->route('orders.show', $orderId);
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order)
    {
        $order->load(['customer', 'supplier', 'warehouse', 'orderItems.product']);
        
        return view('orders.show', compact('order'));
    }

    /**
     * Update order status.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
        ]);
        
        if ($validated['status'] === 'completed') {
            $order->complete();
            return back()->with('success', 'Order completed and inventory updated.');
        } else {
            $order->status = $validated['status'];
            $order->save();
            return back()->with('success', 'Order status updated.');
        }
    }

    /**
     * Cancel an order.
     */
    public function cancel(Order $order)
    {
        if ($order->status === 'completed') {
            return back()->with('error', 'Cannot cancel a completed order.');
        }
        
        \DB::transaction(function () use ($order) {
            // Release reserved stock for sales orders
            if ($order->type === 'sale' && $order->status !== 'cancelled') {
                foreach ($order->orderItems as $item) {
                    $inventory = Inventory::where('product_id', $item->product_id)
                        ->where('warehouse_id', $order->warehouse_id)
                        ->first();
                    
                    if ($inventory) {
                        $inventory->releaseReservedStock($item->quantity);
                    }
                }
            }
            
            $order->status = 'cancelled';
            $order->save();
        });
        
        return back()->with('success', 'Order cancelled successfully.');
    }
}
