<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with(['category', 'vendor', 'images'])->where('is_active', true);

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        if ($request->boolean('in_stock_only')) {
            $query->where('stock_quantity', '>', 0);
        }

        return response()->json($query->paginate(20));
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load(['category', 'vendor', 'images', 'reviews.user']));
    }

    public function vendorIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Product::query()->with('images');

        if ($user->role === User::ROLE_VENDOR) {
            $query->where('vendor_id', $user->id);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['required', 'string', 'max:80', 'unique:products,sku'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['url'],
            'vendor_id' => ['sometimes', Rule::exists('users', 'id')],
        ]);

        $user = $request->user();
        $validated['vendor_id'] = $user->role === User::ROLE_ADMIN ? ($validated['vendor_id'] ?? $user->id) : $user->id;

        $product = Product::query()->create($validated);

        foreach ($validated['images'] ?? [] as $index => $imageUrl) {
            ProductImage::query()->create([
                'product_id' => $product->id,
                'image_url' => $imageUrl,
                'is_primary' => $index === 0,
            ]);
        }

        return response()->json($product->load('images'), 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== User::ROLE_ADMIN && $product->vendor_id !== $user->id) {
            return response()->json(['message' => 'You can only manage your own products.'], 403);
        }

        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sku' => ['sometimes', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product->id)],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['url'],
        ]);

        $product->update($validated);

        if (array_key_exists('images', $validated)) {
            $product->images()->delete();

            foreach ($validated['images'] as $index => $imageUrl) {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'image_url' => $imageUrl,
                    'is_primary' => $index === 0,
                ]);
            }
        }

        return response()->json($product->fresh()->load('images'));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== User::ROLE_ADMIN && $product->vendor_id !== $user->id) {
            return response()->json(['message' => 'You can only manage your own products.'], 403);
        }

        $product->delete();

        return response()->json(status: 204);
    }
}
