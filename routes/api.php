<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HelloController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\BatchController;

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