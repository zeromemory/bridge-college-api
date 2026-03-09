<?php

use App\Http\Controllers\Api\V1\AdmissionController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ProgramController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public data routes
    Route::get('/programs', [ProgramController::class, 'index']);
    Route::get('/programs/{slug}', [ProgramController::class, 'show']);
    Route::get('/branches', [BranchController::class, 'index']);
    Route::get('/branches/{id}', [BranchController::class, 'show']);

    // Public auth routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Email verification
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/resend', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:3,1');

    // Password reset
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:3,1');

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Student dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Admission wizard
        Route::post('/applications', [AdmissionController::class, 'store']);
        Route::put('/applications/{id}', [AdmissionController::class, 'update']);
        Route::get('/applications/{id}', [AdmissionController::class, 'show']);
        Route::put('/applications/{id}/personal-details', [AdmissionController::class, 'updatePersonalDetails']);
        Route::put('/applications/{id}/extras', [AdmissionController::class, 'updateExtras']);
        Route::post('/applications/{id}/documents', [AdmissionController::class, 'uploadDocument']);
        Route::delete('/applications/{id}/documents/{documentId}', [AdmissionController::class, 'deleteDocument']);
        Route::get('/applications/{id}/review', [AdmissionController::class, 'review']);
        Route::post('/applications/{id}/submit', [AdmissionController::class, 'submit']);

        // Admin routes
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('/stats', [AdminController::class, 'stats']);

            // Application management
            Route::get('/applications', [AdminController::class, 'applications']);
            Route::get('/applications/{id}', [AdminController::class, 'showApplication']);
            Route::post('/applications/{id}/accept', [AdminController::class, 'acceptApplication']);
            Route::post('/applications/{id}/reject', [AdminController::class, 'rejectApplication']);
            Route::post('/applications/{id}/toggle-fee', [AdminController::class, 'toggleFeeStatus']);
            Route::get('/documents/{id}/download', [AdminController::class, 'downloadDocument']);

            // Student management
            Route::get('/students', [AdminController::class, 'students']);
            Route::get('/students/{id}', [AdminController::class, 'showStudent']);
            Route::post('/students/{id}/toggle-status', [AdminController::class, 'toggleStudentStatus']);
        });
    });
});
