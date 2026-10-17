<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\HelloController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ServiceController;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

});


/*
|--------------------------------------------------------------------------
| Public APIs
|--------------------------------------------------------------------------
*/

Route::get('/hello', [HelloController::class, 'hello']);

Route::get('/public/batches', [BatchController::class, 'publicIndex']);
Route::get('/public/batches/{slug}', [BatchController::class, 'publicShow']);

Route::get('/public/courses', [CourseController::class, 'publicIndex']);
Route::get('/public/courses/{slug}', [CourseController::class, 'publicShow']);

Route::get('/public/services', [ServiceController::class, 'publicIndex']);
Route::get('/public/services/{slug}', [ServiceController::class, 'publicShow']);

Route::post('/contact', [ContactMessageController::class, 'store']);


/*
|--------------------------------------------------------------------------
| Admin APIs
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource('products', ProductController::class);
    Route::apiResource('batches', BatchController::class);
    Route::apiResource('courses', CourseController::class);
    Route::apiResource('services', ServiceController::class);

    Route::apiResource(
        'contact-messages',
        ContactMessageController::class
    );
});