<?php

use App\Http\Controllers\API\AdminDashboardController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CertificateController;
use App\Http\Controllers\API\CourseController;
use App\Http\Controllers\API\InstructorController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\RecipeController;
use App\Http\Controllers\API\RegistrationController;
use App\Http\Controllers\API\StudentController;
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

// API Version Prefix
Route::prefix('v1')->group(function () {
    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        
        // Protected Authentication Routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('user', [AuthController::class, 'user']);
        });
    });

    // Courses Routes
    Route::get('courses', [CourseController::class, 'index']);
    Route::get('courses/{id}', [CourseController::class, 'show']);
    Route::get('courses/{id}/recipes', [CourseController::class, 'getRecipes']);
    Route::get('courses/{id}/availability', [CourseController::class, 'checkAvailability']);
    
    // Protected Course Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('courses', [CourseController::class, 'store']);
        Route::put('courses/{id}', [CourseController::class, 'update']);
        Route::delete('courses/{id}', [CourseController::class, 'destroy']);
        Route::post('courses/{id}/recipes', [CourseController::class, 'addRecipe']);
        Route::get('courses/{id}/students', [CourseController::class, 'getStudents']);
        Route::get('courses/{id}/attendance', [CourseController::class, 'getAttendance']);
        Route::put('courses/{id}/publish', [CourseController::class, 'publish']);
        Route::put('courses/{id}/cancel', [CourseController::class, 'cancel']);
    });

    // Recipes Routes
    Route::get('recipes', [RecipeController::class, 'index']);
    Route::get('recipes/{id}', [RecipeController::class, 'show']);
    
    // Protected Recipe Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('recipes', [RecipeController::class, 'store']);
        Route::put('recipes/{id}', [RecipeController::class, 'update']);
        Route::delete('recipes/{id}', [RecipeController::class, 'destroy']);
        Route::post('recipes/{id}/images', [RecipeController::class, 'uploadImage']);
    });

    // Students Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('students', [StudentController::class, 'index']);
        Route::post('students', [StudentController::class, 'store']);
        Route::get('students/{id}', [StudentController::class, 'show']);
        Route::put('students/{id}', [StudentController::class, 'update']);
        Route::delete('students/{id}', [StudentController::class, 'destroy']);
        Route::get('students/{id}/courses', [StudentController::class, 'getCourses']);
        Route::get('students/{id}/certificates', [StudentController::class, 'getCertificates']);
    });

    // Instructors Routes
    Route::get('instructors', [InstructorController::class, 'index']);
    Route::get('instructors/{id}', [InstructorController::class, 'show']);
    
    // Protected Instructor Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('instructors', [InstructorController::class, 'store']);
        Route::put('instructors/{id}', [InstructorController::class, 'update']);
        Route::delete('instructors/{id}', [InstructorController::class, 'destroy']);
    });

    // Registration Routes
    Route::get('courses/{id}/availability', [RegistrationController::class, 'checkAvailability']);
    
    // Protected Registration Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('courses/{id}/register', [RegistrationController::class, 'register']);
        Route::put('registrations/{id}/verify', [RegistrationController::class, 'verify']);
        Route::post('registrations/{id}/cancel', [RegistrationController::class, 'cancel']);
    });

    // Payment Routes
    Route::post('payments/webhook', [PaymentController::class, 'webhook']);
    
    // Protected Payment Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('payments', [PaymentController::class, 'store']);
        Route::get('payments/{id}', [PaymentController::class, 'show']);
        Route::put('payments/{id}/verify', [PaymentController::class, 'verify']);
        Route::get('payments/report', [PaymentController::class, 'report']);
    });

    // Certificate Routes
    Route::get('certificates/verify/{certificate_number}', [CertificateController::class, 'verify']);
    
    // Protected Certificate Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('certificates/generate/{registration_id}', [CertificateController::class, 'generate']);
        Route::get('certificates/{id}', [CertificateController::class, 'show']);
    });

    // Admin Dashboard Routes
    Route::middleware(['auth:sanctum', 'role:admin|super-admin|course-administrator'])->prefix('admin')->group(function () {
        Route::get('stats', [AdminDashboardController::class, 'stats']);
        Route::get('courses/upcoming', [AdminDashboardController::class, 'upcomingCourses']);
        Route::get('courses/active', [AdminDashboardController::class, 'activeCourses']);
        Route::get('payments/pending', [AdminDashboardController::class, 'pendingPayments']);
        Route::get('registrations/new', [AdminDashboardController::class, 'newRegistrations']);
    });
});