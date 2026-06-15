<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->resolveCart($request)->load('items.product.images'));
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);

        if (! $product->is_active || $product->stock_quantity < $validated['quantity']) {
            return response()->json(['message' => 'Requested product quantity is unavailable.'], 422);
        }

        $cart = $this->resolveCart($request);

        $item = CartItem::query()->updateOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $product->id],
            ['quantity' => $validated['quantity'], 'unit_price' => $product->price]
        );

        return response()->json($item->load('product'));
    }

    public function updateItem(Request $request, CartItem $item): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($item->cart->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Cart item does not belong to current user.'], 403);
        }

        if ($item->product->stock_quantity < $validated['quantity']) {
            return response()->json(['message' => 'Requested quantity exceeds stock.'], 422);
        }

        $item->update(['quantity' => $validated['quantity']]);

        return response()->json($item->fresh()->load('product'));
    }

    public function removeItem(Request $request, CartItem $item): JsonResponse
    {
        if ($item->cart->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Cart item does not belong to current user.'], 403);
        }

        $item->delete();

        return response()->json(status: 204);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $cart->items()->delete();

        return response()->json(status: 204);
    }

    private function resolveCart(Request $request): Cart
    {
        return Cart::query()->firstOrCreate(['user_id' => $request->user()->id]);
    }
}
