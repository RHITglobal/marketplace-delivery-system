<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VendorCommission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@marketplace.test'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'is_verified' => true,
            ]
        );

        $vendor = User::query()->firstOrCreate(
            ['email' => 'vendor@marketplace.test'],
            [
                'name' => 'Sample Vendor',
                'password' => Hash::make('password'),
                'role' => User::ROLE_VENDOR,
                'is_verified' => true,
            ]
        );

        $customer = User::query()->firstOrCreate(
            ['email' => 'customer@marketplace.test'],
            [
                'name' => 'Sample Customer',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CUSTOMER,
                'is_verified' => true,
            ]
        );

        $agent = User::query()->firstOrCreate(
            ['email' => 'agent@marketplace.test'],
            [
                'name' => 'Delivery Agent',
                'password' => Hash::make('password'),
                'role' => User::ROLE_DELIVERY_AGENT,
                'is_verified' => true,
            ]
        );

        $category = Category::query()->firstOrCreate([
            'slug' => 'grocery-default',
        ], [
            'name' => 'Grocery',
            'description' => 'Daily essentials',
        ]);

        $product = Product::query()->firstOrCreate(
            ['sku' => 'SKU-APPLE-001'],
            [
                'vendor_id' => $vendor->id,
                'category_id' => $category->id,
                'name' => 'Fresh Apples',
                'description' => 'Farm fresh apples',
                'price' => 4.99,
                'stock_quantity' => 200,
                'is_active' => true,
            ]
        );

        $order = Order::query()->firstOrCreate(
            ['order_number' => 'ORD-SEED-001'],
            [
                'customer_id' => $customer->id,
                'status' => 'delivered',
                'payment_status' => 'paid',
                'total_amount' => 49.90,
                'delivery_address' => '123 Demo Street',
                'placed_at' => Carbon::now()->subDay(),
            ]
        );

        OrderItem::query()->firstOrCreate(
            ['order_id' => $order->id, 'product_id' => $product->id],
            [
                'vendor_id' => $vendor->id,
                'quantity' => 10,
                'unit_price' => 4.99,
                'total_price' => 49.90,
            ]
        );

        Payment::query()->firstOrCreate(
            ['order_id' => $order->id],
            [
                'method' => 'card',
                'status' => 'completed',
                'amount' => $order->total_amount,
                'transaction_reference' => 'TXN-SEED-'.Str::upper(Str::random(6)),
                'paid_at' => Carbon::now()->subDay(),
            ]
        );

        Delivery::query()->firstOrCreate(
            ['order_id' => $order->id],
            [
                'agent_id' => $agent->id,
                'status' => 'delivered',
                'current_location' => 'Delivered',
                'picked_at' => Carbon::now()->subDay()->subHour(),
                'delivered_at' => Carbon::now()->subDay(),
            ]
        );

        Review::query()->firstOrCreate(
            ['product_id' => $product->id, 'user_id' => $customer->id],
            ['rating' => 5, 'comment' => 'Great quality and quick delivery.']
        );

        VendorCommission::query()->firstOrCreate(
            ['order_id' => $order->id, 'vendor_id' => $vendor->id],
            ['commission_rate' => 10, 'commission_amount' => 4.99]
        );

        SystemSetting::query()->updateOrCreate(['key' => 'commission_rate'], ['value' => '10']);
        SystemSetting::query()->updateOrCreate(['key' => 'currency'], ['value' => 'USD']);
        SystemSetting::query()->updateOrCreate(['key' => 'support_email'], ['value' => 'support@marketplace.test']);

    }
}
