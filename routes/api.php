<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\HelloController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuizQuestionController;
/*
|--------------------------------------------------------------------------
| PUBLIC routes (anyone can use them)
|--------------------------------------------------------------------------
*/

Route::get('/hello', [HelloController::class, 'hello']);

// Admin login: 5 attempts per minute for the same email and IP
// (the "login" limit is defined in AppServiceProvider)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Website: read only, found by slug
Route::get('/public/batches', [BatchController::class, 'publicIndex']);
Route::get('/public/batches/{slug}', [BatchController::class, 'publicShow'])
    ->where('slug', '[a-z0-9\-]+');

Route::get('/public/courses', [CourseController::class, 'publicIndex']);
Route::get('/public/courses/{slug}', [CourseController::class, 'publicShow'])
    ->where('slug', '[a-z0-9\-]+');

Route::get('/public/services', [ServiceController::class, 'publicIndex']);
Route::get('/public/services/{slug}', [ServiceController::class, 'publicShow'])
    ->where('slug', '[a-z0-9\-]+');

// Website contact form: max 5 messages per 10 minutes per IP
Route::post('/contact', [ContactMessageController::class, 'store'])
    ->middleware('throttle:5,10');

/*
|--------------------------------------------------------------------------
| ADMIN routes (the admin must be logged in)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::apiResource('products', ProductController::class);
    Route::apiResource('batches', BatchController::class);
    Route::apiResource('courses', CourseController::class);
    Route::apiResource('services', ServiceController::class);
    Route::apiResource('admin/quizzes', QuizController::class);

    /*
|--------------------------------------------------------------------------
| Quiz Questions
|--------------------------------------------------------------------------
*/

Route::apiResource('admin/questions', QuestionController::class);

Route::post(
    'admin/quizzes/{quiz}/questions',
    [QuizQuestionController::class, 'store']
);

Route::put(
    'admin/quizzes/{quiz}/questions/order',
    [QuizQuestionController::class, 'updateOrder']
);

Route::put(
    'admin/quizzes/{quiz}/questions/{question}',
    [QuizQuestionController::class, 'update']
);

Route::delete(
    'admin/quizzes/{quiz}/questions/{question}',
    [QuizQuestionController::class, 'destroy']
);

    // The admin can only list, view and delete messages
    // (new messages come from the public /contact route above)
    Route::apiResource('contact-messages', ContactMessageController::class)
        ->only(['index', 'show', 'destroy']);
});