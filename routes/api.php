
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
use App\Http\Controllers\Api\QuizResultController;
use App\Http\Controllers\Api\CourseSectionController;
use App\Http\Controllers\Api\CourseLessonController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\StudentAuthController;
use App\Http\Controllers\Api\StudentController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/hello', [HelloController::class, 'hello']);
Route::get('/csrf-cookie', fn () => response()->json(['success' => true]));
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::get('/public/batches', [BatchController::class, 'publicIndex']);

Route::get('/public/batches/{slug}', [BatchController::class, 'publicShow'])
    ->where('slug', '[a-z0-9\-]+');

Route::get('/public/courses', [CourseController::class, 'publicIndex']);

Route::get('/public/courses/{slug}', [CourseController::class, 'publicShow'])
    ->where('slug', '[a-z0-9\-]+');

Route::get(
    '/public/courses/{slug}/lessons/{lesson}/video',
    [CourseController::class, 'streamLessonVideo']
)->name('public.courses.lessons.video');

Route::get('/public/services', [ServiceController::class, 'publicIndex']);

Route::get('/public/services/{slug}', [ServiceController::class, 'publicShow'])
    ->where('slug', '[a-z0-9\-]+');

Route::post('/contact', [ContactMessageController::class, 'store'])
    ->middleware('throttle:5,10');

Route::get('/public/quizzes', [QuizController::class, 'publicIndex']);

Route::get('/public/quizzes/{id}', [QuizController::class, 'publicShow'])
    ->whereNumber('id');


/*
|--------------------------------------------------------------------------
| STUDENT AUTHENTICATION
|--------------------------------------------------------------------------
*/

Route::prefix('student')
    ->middleware('throttle:10,1')
    ->group(function () {
        Route::post('/register', [StudentAuthController::class, 'register']);
        Route::post('/login', [StudentAuthController::class, 'login']);
    });


/*
|--------------------------------------------------------------------------
| AUTHENTICATED STUDENT ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'role:user',
])->prefix('student')->group(function () {
    Route::get('/user', [StudentAuthController::class, 'user']);
    Route::post('/logout', [StudentAuthController::class, 'logout']);
});


/*
|--------------------------------------------------------------------------
| STUDENT QUIZ ATTEMPTS
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'role:user',
])->group(function () {
    Route::post(
        '/quizzes/{quiz}/attempts',
        [QuizAttemptController::class, 'store']
    );

    Route::get(
        '/attempts/{attempt}',
        [QuizAttemptController::class, 'show']
    );

    Route::put(
        '/attempts/{attempt}/answers',
        [QuizAttemptController::class, 'saveAnswer']
    );

    Route::post(
        '/attempts/{attempt}/submit',
        [QuizAttemptController::class, 'submit']
    );

    Route::get(
        '/attempts/{attempt}/result',
        [QuizAttemptController::class, 'result']
    );
});


/*
|--------------------------------------------------------------------------
| AUTHENTICATED ADMIN AND TEACHER ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'role:admin,teacher',
])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', [AuthController::class, 'user']);


    /*
    |--------------------------------------------------------------------------
    | COURSES AND BATCHES
    |--------------------------------------------------------------------------
    */

    Route::apiResource('batches', BatchController::class);

    Route::apiResource('courses', CourseController::class);


    /*
    |--------------------------------------------------------------------------
    | COURSE SECTIONS
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'courses.sections',
        CourseSectionController::class
    );


    /*
    |--------------------------------------------------------------------------
    | VIDEO LESSONS
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'sections.lessons',
        CourseLessonController::class
    )->only([
        'index',
        'show',
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | SECURE ADMIN VIDEO PLAYBACK
    |--------------------------------------------------------------------------
    */

    Route::get(
        'sections/{section}/lessons/{lesson}/video',
        [CourseLessonController::class, 'video']
    )->name('admin.sections.lessons.video');


    /*
    |--------------------------------------------------------------------------
    | QUIZZES
    |--------------------------------------------------------------------------
    */

    Route::apiResource('quizzes', QuizController::class);


    /*
    |--------------------------------------------------------------------------
    | QUIZ RESULTS
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/quiz-results',
        [QuizResultController::class, 'index']
    );

    Route::get(
        '/quiz-results/{attempt}',
        [QuizResultController::class, 'show']
    );


    /*
    |--------------------------------------------------------------------------
    | MAIN ADMIN ONLY
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {

        // Global website content.
        Route::apiResource('products', ProductController::class);

        Route::apiResource('services', ServiceController::class);


        // Contact messages.
        Route::apiResource(
            'contact-messages',
            ContactMessageController::class
        )->only([
            'index',
            'show',
            'destroy',
        ]);


        // Student management.
        Route::apiResource('students', StudentController::class)
            ->only([
                'index',
                'show',
                'update',
                'destroy',
            ]);

        Route::patch(
            '/students/{student}/status',
            [StudentController::class, 'updateStatus']
        );


        // Teacher management.
        Route::prefix('admin-users')->group(function () {

            Route::get('/', [AdminUserController::class, 'index']);

            Route::post('/', [AdminUserController::class, 'store']);

            Route::get('/{admin}', [AdminUserController::class, 'show']);

            Route::put('/{admin}', [AdminUserController::class, 'update']);

            Route::patch(
                '/{admin}/status',
                [AdminUserController::class, 'updateStatus']
            );
        });
    });
});
