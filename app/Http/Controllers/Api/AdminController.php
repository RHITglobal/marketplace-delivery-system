<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'users' => User::query()->count(),
            'vendors' => User::query()->where('role', User::ROLE_VENDOR)->count(),
            'orders' => Order::query()->count(),
            'pending_vendor_verifications' => User::query()->where('role', User::ROLE_VENDOR)->where('is_verified', false)->count(),
            'revenue' => Payment::query()->where('status', 'completed')->sum('amount'),
        ]);
    }

    public function users(): JsonResponse
    {
        return response()->json(User::query()->latest()->paginate(30));
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'is_verified' => ['sometimes', 'boolean'],
        ]);

        $user->update($validated);

        return response()->json($user->fresh());
    }

    public function vendors(Request $request): JsonResponse
    {
        $verified = $request->query('verified');

        $query = User::query()->where('role', User::ROLE_VENDOR);

        if ($verified !== null) {
            $query->where('is_verified', filter_var($verified, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->latest()->paginate(30));
    }

    public function verifyVendor(User $user): JsonResponse
    {
        if ($user->role !== User::ROLE_VENDOR) {
            return response()->json(['message' => 'Selected user is not a vendor.'], 422);
        }

        $user->update(['is_verified' => true]);

        return response()->json($user->fresh());
    }

    public function analytics(): JsonResponse
    {
        return response()->json([
            'orders_by_status' => Order::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'revenue_by_method' => Payment::query()
                ->selectRaw('method, SUM(amount) as total')
                ->where('status', 'completed')
                ->groupBy('method')
                ->pluck('total', 'method'),
        ]);
    }

    public function settings(): JsonResponse
    {
        return response()->json(SystemSetting::query()->get()->pluck('value', 'key'));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string'],
        ]);

        foreach ($validated['settings'] as $key => $value) {
            SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return $this->settings();
    }
}
