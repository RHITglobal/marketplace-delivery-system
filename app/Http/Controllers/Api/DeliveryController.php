<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DeliveryController extends Controller
{
    public function assign(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => ['required', 'exists:users,id'],
            'estimated_delivery_at' => ['nullable', 'date'],
        ]);

        $agent = User::query()->findOrFail($validated['agent_id']);

        if ($agent->role !== User::ROLE_DELIVERY_AGENT) {
            return response()->json(['message' => 'Assigned user is not a delivery agent.'], 422);
        }

        $delivery = Delivery::query()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'agent_id' => $agent->id,
                'status' => 'assigned',
                'estimated_delivery_at' => $validated['estimated_delivery_at'] ?? null,
            ]
        );

        $order->update(['status' => 'processing']);

        return response()->json($delivery->load(['order', 'agent']));
    }

    public function myDeliveries(Request $request): JsonResponse
    {
        $query = Delivery::query()->with('order.customer');
        $user = $request->user();

        if ($user->role === User::ROLE_DELIVERY_AGENT) {
            $query->where('agent_id', $user->id);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function updateStatus(Request $request, Delivery $delivery): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:assigned,picked_up,in_transit,delivered,failed'],
            'current_location' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        if ($user->role === User::ROLE_DELIVERY_AGENT && $delivery->agent_id !== $user->id) {
            return response()->json(['message' => 'You are not assigned to this delivery.'], 403);
        }

        $delivery->fill($validated);

        if ($validated['status'] === 'picked_up' && ! $delivery->picked_at) {
            $delivery->picked_at = Carbon::now();
        }

        if ($validated['status'] === 'delivered') {
            $delivery->delivered_at = Carbon::now();
            $delivery->order()->update(['status' => 'delivered']);
        }

        if ($validated['status'] === 'in_transit') {
            $delivery->order()->update(['status' => 'out_for_delivery']);
        }

        $delivery->save();

        return response()->json($delivery->fresh());
    }

    public function track(Order $order): JsonResponse
    {
        $delivery = Delivery::query()->with('agent')->where('order_id', $order->id)->first();

        if (! $delivery) {
            return response()->json(['message' => 'Delivery has not been assigned yet.'], 404);
        }

        return response()->json($delivery);
    }

    public function metrics(Request $request): JsonResponse
    {
        $query = Delivery::query()->whereNotNull('delivered_at');

        if ($request->user()->role === User::ROLE_DELIVERY_AGENT) {
            $query->where('agent_id', $request->user()->id);
        }

        $deliveries = $query->get();

        $averageMinutes = $deliveries
            ->filter(fn ($delivery) => $delivery->picked_at && $delivery->delivered_at)
            ->avg(fn ($delivery) => Carbon::parse($delivery->picked_at)->diffInMinutes(Carbon::parse($delivery->delivered_at)));

        return response()->json([
            'completed_deliveries' => $deliveries->count(),
            'average_delivery_minutes' => $averageMinutes ? round($averageMinutes, 2) : 0,
            'failed_deliveries' => Delivery::query()->where('status', 'failed')->count(),
        ]);
    }
}
