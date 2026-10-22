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
use App\Http\Controllers\Api\QuizAttemptController;

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

Route::get('/public/quizzes', [QuizController::class, 'publicIndex']);
Route::get('/public/quizzes/{id}', [QuizController::class, 'publicShow'])
    ->whereNumber('id');

    Route::post('/quizzes/{quiz}/attempts', [
    QuizAttemptController::class,
    'store',
]);

Route::get('/attempts/{attempt}', [
    QuizAttemptController::class,
    'show',
]);

Route::put('/attempts/{attempt}/answers', [
    QuizAttemptController::class,
    'saveAnswer',
]);

Route::post('/attempts/{attempt}/submit', [
    QuizAttemptController::class,
    'submit',
]);

Route::get('/attempts/{attempt}/result', [
    QuizAttemptController::class,
    'result',
]);

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

    Route::apiResource('quizzes', QuizController::class);

    /*
    |--------------------------------------------------------------------------
    | Quiz Attempts
    |--------------------------------------------------------------------------
    | These are for authenticated users/students.
    |
    */

    // Route::post('/quizzes/{quiz}/attempts',[QuizAttemptController::class,'store']);
    // Route::get('/attempts/{attempt}',[QuizAttemptController::class,'show']);
    // Route::put('/attempts/{attempt}/answers',[QuizAttemptController::class,'saveAnswer']);
    // Route::post('/attempts/{attempt}/submit',[QuizAttemptController::class,'submit']);
    // Route::get('/attempts/{attempt}/result',[QuizAttemptController::class,'result']);

    // The admin can only list, view and delete messages
    // (new messages come from the public /contact route above)
    Route::apiResource('contact-messages', ContactMessageController::class)
        ->only(['index', 'show', 'destroy']);
});