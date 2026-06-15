<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::prefix('catalog')->group(function () {
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::get('products/{product}/reviews', [ReviewController::class, 'index']);
    });

    Route::middleware('auth.jwt')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);

        Route::get('cart', [CartController::class, 'show']);
        Route::post('cart/items', [CartController::class, 'addItem']);
        Route::patch('cart/items/{item}', [CartController::class, 'updateItem']);
        Route::delete('cart/items/{item}', [CartController::class, 'removeItem']);
        Route::delete('cart', [CartController::class, 'clear']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'place']);
        Route::get('orders/{order}', [OrderController::class, 'show']);

        Route::get('payments/methods', [PaymentController::class, 'methods']);
        Route::post('orders/{order}/payments', [PaymentController::class, 'process']);
        Route::get('orders/{order}/invoice', [PaymentController::class, 'invoice']);

        Route::post('products/{product}/reviews', [ReviewController::class, 'store']);
        Route::patch('reviews/{review}', [ReviewController::class, 'update']);
        Route::delete('reviews/{review}', [ReviewController::class, 'destroy']);

        Route::get('deliveries/tracking/{order}', [DeliveryController::class, 'track']);

        Route::middleware('role:vendor,admin')->group(function () {
            Route::get('vendor/products', [ProductController::class, 'vendorIndex']);
            Route::post('vendor/products', [ProductController::class, 'store']);
            Route::put('vendor/products/{product}', [ProductController::class, 'update']);
            Route::delete('vendor/products/{product}', [ProductController::class, 'destroy']);
            Route::patch('vendor/orders/{order}/status', [OrderController::class, 'updateStatus']);
        });

        Route::middleware('role:delivery_agent,admin')->group(function () {
            Route::get('deliveries', [DeliveryController::class, 'myDeliveries']);
            Route::patch('deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus']);
            Route::get('deliveries/metrics/summary', [DeliveryController::class, 'metrics']);
        });

        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('dashboard', [AdminController::class, 'dashboard']);
            Route::get('users', [AdminController::class, 'users']);
            Route::patch('users/{user}', [AdminController::class, 'updateUser']);
            Route::get('vendors', [AdminController::class, 'vendors']);
            Route::patch('vendors/{user}/verify', [AdminController::class, 'verifyVendor']);
            Route::get('analytics', [AdminController::class, 'analytics']);
            Route::get('settings', [AdminController::class, 'settings']);
            Route::put('settings', [AdminController::class, 'updateSettings']);
            Route::post('categories', [CategoryController::class, 'store']);
            Route::put('categories/{category}', [CategoryController::class, 'update']);
            Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
            Route::post('orders/{order}/assign-delivery', [DeliveryController::class, 'assign']);
        });
    });
});
