<?php

namespace App\Http\Controllers;

use App\Models\ReturnModel;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    /**
     * Display a listing of returns.
     */
    public function index(Request $request)
    {
        $query = ReturnModel::with(['order', 'product', 'warehouse']);
        
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }
        
        $returns = $query->latest()->paginate(15);
        
        return view('returns.index', compact('returns'));
    }

    /**
     * Show the form for creating a new return.
     */
    public function create(Request $request)
    {
        $orderId = $request->get('order_id');
        $order = null;
        
        if ($orderId) {
            $order = Order::with('orderItems.product')->findOrFail($orderId);
        }
        
        return view('returns.create', compact('order'));
    }

    /**
     * Store a newly created return.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string',
        ]);
        
        // Verify the order item exists
        $orderItem = OrderItem::where('order_id', $validated['order_id'])
            ->where('product_id', $validated['product_id'])
            ->firstOrFail();
        
        if ($validated['quantity'] > $orderItem->quantity) {
            return back()->withErrors(['quantity' => 'Return quantity cannot exceed ordered quantity.']);
        }
        
        $order = Order::findOrFail($validated['order_id']);
        
        $returnNumber = ReturnModel::generateReturnNumber();
        $refundAmount = $orderItem->unit_price * $validated['quantity'];
        
        $return = ReturnModel::create([
            'return_number' => $returnNumber,
            'order_id' => $validated['order_id'],
            'product_id' => $validated['product_id'],
            'warehouse_id' => $order->warehouse_id,
            'quantity' => $validated['quantity'],
            'reason' => $validated['reason'],
            'status' => 'pending',
            'refund_amount' => $refundAmount,
            'return_date' => now(),
        ]);
        
        return redirect()->route('returns.show', $return)
            ->with('success', 'Return request created successfully.');
    }

    /**
     * Display the specified return.
     */
    public function show(ReturnModel $return)
    {
        $return->load(['order.customer', 'product', 'warehouse']);
        
        return view('returns.show', compact('return'));
    }

    /**
     * Approve a return.
     */
    public function approve(ReturnModel $return)
    {
        if ($return->status !== 'pending') {
            return back()->with('error', 'Only pending returns can be approved.');
        }
        
        $return->approve();
        
        return back()->with('success', 'Return approved and inventory updated.');
    }

    /**
     * Reject a return.
     */
    public function reject(ReturnModel $return)
    {
        if ($return->status !== 'pending') {
            return back()->with('error', 'Only pending returns can be rejected.');
        }
        
        $return->status = 'rejected';
        $return->save();
        
        return back()->with('success', 'Return rejected.');
    }

    /**
     * Process refund for a return.
     */
    public function processRefund(ReturnModel $return)
    {
        try {
            $return->processRefund();
            return back()->with('success', 'Refund processed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
