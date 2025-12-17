<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Profile routes
Route::get('/profile', [ProfileController::class, 'show'])->middleware('auth:sanctum')->name('profile.show');

// Order routes
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::post('/orders', [OrderController::class, 'store'])->middleware('auth:sanctum')->name('orders.store');
Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('auth:sanctum')->name('orders.cancel');

