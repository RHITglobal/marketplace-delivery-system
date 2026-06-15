<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Order::query()->with(['items.product', 'customer']);

        if ($user->role === User::ROLE_CUSTOMER) {
            $query->where('customer_id', $user->id);
        } elseif ($user->role === User::ROLE_VENDOR) {
            $query->whereHas('items', fn ($builder) => $builder->where('vendor_id', $user->id));
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $isOwner = $user->role === User::ROLE_CUSTOMER && $order->customer_id === $user->id;
        $isVendor = $user->role === User::ROLE_VENDOR && $order->items()->where('vendor_id', $user->id)->exists();

        if (! ($isOwner || $isVendor || $user->role === User::ROLE_ADMIN || $user->role === User::ROLE_DELIVERY_AGENT)) {
            return response()->json(['message' => 'Unauthorized to view this order.'], 403);
        }

        return response()->json($order->load(['items.product', 'payments', 'deliveries.agent', 'customer']));
    }

    public function place(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_address' => ['required', 'string'],
            'payment_method' => ['required', 'string', 'in:cash,card,wallet,bank_transfer'],
        ]);

        $cart = Cart::query()->with('items.product')->where('user_id', $request->user()->id)->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty.'], 422);
        }

        $order = DB::transaction(function () use ($cart, $validated, $request) {
            $total = $cart->items->sum(fn ($item) => $item->quantity * $item->unit_price);

            $order = Order::query()->create([
                'customer_id' => $request->user()->id,
                'order_number' => 'ORD-'.Str::upper(Str::random(10)),
                'status' => 'confirmed',
                'payment_status' => 'pending',
                'total_amount' => $total,
                'delivery_address' => $validated['delivery_address'],
                'placed_at' => Carbon::now(),
            ]);

            foreach ($cart->items as $item) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'vendor_id' => $item->product->vendor_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->quantity * $item->unit_price,
                ]);

                $item->product->decrement('stock_quantity', $item->quantity);
            }

            Payment::query()->create([
                'order_id' => $order->id,
                'method' => $validated['payment_method'],
                'status' => 'pending',
                'amount' => $total,
            ]);

            $commissionRate = 10;
            $vendorTotals = $order->items()->selectRaw('vendor_id, SUM(total_price) as total')->groupBy('vendor_id')->get();

            foreach ($vendorTotals as $vendorTotal) {
                VendorCommission::query()->create([
                    'order_id' => $order->id,
                    'vendor_id' => $vendorTotal->vendor_id,
                    'commission_rate' => $commissionRate,
                    'commission_amount' => round(($vendorTotal->total * $commissionRate) / 100, 2),
                ]);
            }

            $cart->items()->delete();

            return $order;
        });

        try {
            Mail::raw(
                "Your order {$order->order_number} has been placed successfully.",
                fn ($message) => $message->to($request->user()->email)->subject('Order Confirmation')
            );
        } catch (\Throwable $exception) {
            Log::warning('Order confirmation email could not be sent.', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json($order->load(['items', 'payments']), 201);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,processing,out_for_delivery,delivered,cancelled'],
            'payment_status' => ['sometimes', 'string', 'in:pending,paid,failed,refunded'],
        ]);

        $order->update($validated);

        return response()->json($order->fresh());
    }
}
