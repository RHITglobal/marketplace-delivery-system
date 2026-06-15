<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function methods(): JsonResponse
    {
        return response()->json([
            ['key' => 'cash', 'label' => 'Cash on Delivery'],
            ['key' => 'card', 'label' => 'Credit / Debit Card'],
            ['key' => 'wallet', 'label' => 'Wallet'],
            ['key' => 'bank_transfer', 'label' => 'Bank Transfer'],
        ]);
    }

    public function process(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'in:cash,card,wallet,bank_transfer'],
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'method' => $validated['method'],
            'status' => 'completed',
            'amount' => $order->total_amount,
            'transaction_reference' => 'TXN-'.Str::upper(Str::random(12)),
            'paid_at' => Carbon::now(),
        ]);

        $order->update(['payment_status' => 'paid']);

        return response()->json($payment, 201);
    }

    public function invoice(Order $order): JsonResponse
    {
        return response()->json([
            'order_number' => $order->order_number,
            'customer_id' => $order->customer_id,
            'total_amount' => $order->total_amount,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'line_items' => $order->items()->with('product')->get()->map(fn ($item) => [
                'product' => $item->product->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total_price,
            ]),
        ]);
    }
}
