<?php

use App\Http\Controllers\Api\V1\AdmissionController;
use App\Http\Controllers\Api\V1\Admin\AcademicSessionController;
use App\Http\Controllers\Api\V1\Admin\ClassController;
use App\Http\Controllers\Api\V1\Admin\SubjectController;
use App\Http\Controllers\Api\V1\Admin\TeacherController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\Student\StudentLmsController;
use App\Http\Controllers\Api\V1\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Api\V1\Admin\GradebookController as AdminGradebookController;
use App\Http\Controllers\Api\V1\Teacher\AttendanceController as TeacherAttendanceController;
use App\Http\Controllers\Api\V1\Teacher\GradebookController as TeacherGradebookController;
use App\Http\Controllers\Api\V1\Teacher\ClassMaterialController;
use App\Http\Controllers\Api\V1\Teacher\TeacherDashboardController;
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
    Route::post('/validate-reset-token', [AuthController::class, 'validateResetToken'])
        ->middleware('throttle:10,1');

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

            // === LMS: Academic Sessions ===
            Route::get('/academic-sessions', [AcademicSessionController::class, 'index']);
            Route::post('/academic-sessions', [AcademicSessionController::class, 'store']);
            Route::put('/academic-sessions/{session}', [AcademicSessionController::class, 'update']);
            Route::post('/academic-sessions/{session}/activate', [AcademicSessionController::class, 'activate']);

            // === LMS: Subjects ===
            Route::get('/subjects', [SubjectController::class, 'index']);
            Route::post('/subjects', [SubjectController::class, 'store']);
            Route::put('/subjects/{subject}', [SubjectController::class, 'update']);
            Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy']);

            // === LMS: Program Subjects ===
            Route::get('/programs/{program}/subjects', [SubjectController::class, 'programSubjects']);
            Route::post('/programs/{program}/subjects', [SubjectController::class, 'syncProgramSubjects']);

            // === LMS: Teachers ===
            Route::get('/teachers', [TeacherController::class, 'index']);
            Route::post('/teachers', [TeacherController::class, 'store']);
            Route::get('/teachers/{teacher}', [TeacherController::class, 'show']);
            Route::put('/teachers/{teacher}', [TeacherController::class, 'update']);
            Route::post('/teachers/{teacher}/toggle-status', [TeacherController::class, 'toggleStatus']);
            Route::post('/teachers/{teacher}/resend-setup', [TeacherController::class, 'resendSetup']);

            // === LMS: Classes ===
            Route::get('/classes', [ClassController::class, 'index']);
            Route::post('/classes', [ClassController::class, 'store']);
            Route::get('/classes/{class}', [ClassController::class, 'show']);
            Route::put('/classes/{class}', [ClassController::class, 'update']);
            Route::delete('/classes/{class}', [ClassController::class, 'destroy']);

            // === LMS: Class Teacher & Student Management ===
            Route::post('/classes/{class}/assign-teacher', [ClassController::class, 'assignTeacher']);
            Route::post('/classes/{class}/unassign-teacher', [ClassController::class, 'unassignTeacher']);
            Route::get('/classes/{class}/students', [ClassController::class, 'students']);
            Route::post('/classes/{class}/enroll', [ClassController::class, 'enrollStudent']);
            Route::delete('/classes/{class}/unenroll/{studentId}', [ClassController::class, 'unenrollStudent']);

            // === LMS: Admin Attendance ===
            Route::get('/classes/{class}/attendance', [AdminAttendanceController::class, 'index']);
            Route::post('/classes/{class}/attendance', [AdminAttendanceController::class, 'mark']);
            Route::get('/classes/{class}/attendance/monthly', [AdminAttendanceController::class, 'monthly']);

            // === LMS: Admin Gradebook ===
            Route::get('/classes/{class}/results', [AdminGradebookController::class, 'classResults']);
            Route::get('/classes/{class}/subjects/{subject}/results', [AdminGradebookController::class, 'subjectResults']);
            Route::get('/students/{student}/results', [AdminGradebookController::class, 'studentResults']);
        });

        // === LMS: Teacher dashboard ===
        Route::middleware('teacher')->prefix('teacher')->group(function () {
            Route::get('/classes', [TeacherDashboardController::class, 'myClasses']);
            Route::get('/classes/{class}', [TeacherDashboardController::class, 'classDetail']);
            Route::get('/classes/{class}/students', [TeacherDashboardController::class, 'classStudents']);

            Route::get('/classes/{class}/materials', [ClassMaterialController::class, 'index']);
            Route::post('/classes/{class}/materials', [ClassMaterialController::class, 'store']);
            Route::delete('/classes/{class}/materials/{material}', [ClassMaterialController::class, 'destroy']);

            // === LMS: Teacher Attendance ===
            Route::post('/classes/{class}/attendance', [TeacherAttendanceController::class, 'mark']);
            Route::get('/classes/{class}/attendance', [TeacherAttendanceController::class, 'index']);
            Route::get('/classes/{class}/attendance/history', [TeacherAttendanceController::class, 'history']);

            // === LMS: Teacher Gradebook ===
            Route::get('/classes/{class}/assessments', [TeacherGradebookController::class, 'index']);
            Route::post('/classes/{class}/assessments', [TeacherGradebookController::class, 'store']);
            Route::get('/assessments/{assessment}', [TeacherGradebookController::class, 'show']);
            Route::put('/assessments/{assessment}', [TeacherGradebookController::class, 'update']);
            Route::delete('/assessments/{assessment}', [TeacherGradebookController::class, 'destroy']);
            Route::post('/assessments/{assessment}/publish', [TeacherGradebookController::class, 'publish']);
            Route::post('/assessments/{assessment}/unpublish', [TeacherGradebookController::class, 'unpublish']);
            Route::post('/assessments/{assessment}/marks', [TeacherGradebookController::class, 'saveMarks']);
            Route::get('/classes/{class}/students/{student}/grades', [TeacherGradebookController::class, 'studentSummary']);
        });

        // === LMS: Student LMS view ===
        Route::middleware('student')->prefix('student/lms')->group(function () {
            Route::get('/my-class', [StudentLmsController::class, 'myClass']);
            Route::get('/materials/{material}/download', [StudentLmsController::class, 'downloadMaterial']);

            // === LMS: Student Attendance ===
            Route::get('/attendance', [StudentLmsController::class, 'myAttendance']);
            Route::get('/attendance/monthly', [StudentLmsController::class, 'monthlyAttendance']);

            // === LMS: Student Gradebook ===
            Route::get('/grades', [StudentLmsController::class, 'myGrades']);
            Route::get('/grades/{type}', [StudentLmsController::class, 'myGradesByType']);
        });
    });
});
