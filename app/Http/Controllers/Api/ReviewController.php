<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Product $product): JsonResponse
    {
        return response()->json($product->reviews()->with('user')->latest()->paginate(20));
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string'],
        ]);

        $review = Review::query()->updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $request->user()->id],
            $validated
        );

        return response()->json($review->load('user'), 201);
    }

    public function update(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id && $request->user()->role !== User::ROLE_ADMIN) {
            return response()->json(['message' => 'Not allowed to update this review.'], 403);
        }

        $validated = $request->validate([
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'comment' => ['sometimes', 'nullable', 'string'],
        ]);

        $review->update($validated);

        return response()->json($review->fresh()->load('user'));
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id && $request->user()->role !== User::ROLE_ADMIN) {
            return response()->json(['message' => 'Not allowed to delete this review.'], 403);
        }

        $review->delete();

        return response()->json(status: 204);
    }
}
