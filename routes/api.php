<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HelloController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\ContactMessageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/hello', [HelloController::class, 'hello']);

// Public routes (used by the client website, find batch by slug)
Route::get('/public/batches', [BatchController::class, 'publicIndex']);
Route::get('/public/batches/{slug}', [BatchController::class, 'publicShow']);

// Admin routes (find batch by id)
Route::apiResource('products', ProductController::class);
Route::apiResource('batches', BatchController::class);

// Public: max 5 messages per 10 minutes per IP
Route::post('/contact', [ContactMessageController::class, 'store'])
    ->middleware('throttle:5,10');

// Admin only (needs login token)
// Route::middleware('auth:sanctum')->group(function () {
//     Route::apiResource('contact-messages', ContactMessageController::class)
//         ->only(['index', 'destroy']);
// });

// Admin (no login yet, same as your batches and products routes)
Route::apiResource('contact-messages', ContactMessageController::class)
    ->only(['index', 'show','destroy']);