<?php
// routes/api.php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\FileController;
// use App\Http\Controllers\Api\TaskController;
// use App\Http\Controllers\Api\MinuteController;
// use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\MinuteController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OfficeController;

// Auth routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('refresh', [AuthController::class, 'refresh']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::get('me', [AuthController::class, 'me'])->middleware('auth:api');
Route::post('update-profile-picture', [AuthController::class, 'updateProfilePicture'])->middleware('auth:api');

// Password Reset Routes (OTP)
Route::post('forgot-password/send-otp', [AuthController::class, 'sendOtp']);
Route::post('forgot-password/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('forgot-password/reset', [AuthController::class, 'resetPasswordWithOtp']);

// Protected routes
Route::middleware('auth:api')->group(function () {

     Route::post('/change-password', [AuthController::class, 'changePassword']);
        
    // Notifications Route
    Route::get('notifications', [\App\Http\Controllers\AppNotificationController::class, 'index']);
    Route::post('notifications', [\App\Http\Controllers\AppNotificationController::class, 'store']);
    Route::patch('notifications/{id}/read', [\App\Http\Controllers\AppNotificationController::class, 'markAsRead']);

    // ===== CLIENTS =====
    Route::apiResource('clients', ClientController::class);
    Route::post('clients/{id}/upload-profile-picture', [ClientController::class, 'uploadProfilePicture']);
    Route::get('clients/search/{query}', [ClientController::class, 'search']);
    Route::get('clients/{id}/cases', [ClientController::class, 'cases']);
    Route::get('client-portal/cases', [ClientController::class, 'portalCases']);
    Route::get('client-portal/fees', [ClientController::class, 'portalFees']);
    Route::get('client-portal/notifications', [\App\Http\Controllers\AppNotificationController::class, 'clientNotifications']);
    Route::patch('client-portal/notifications/{id}/read', [\App\Http\Controllers\AppNotificationController::class, 'clientMarkAsRead']);
    Route::put('clients/{id}/change-password', [ClientController::class, 'changePassword']);



    Route::prefix('files')->group(function () {
        Route::post('upload', [FileController::class, 'upload']);
        Route::post('attach', [FileController::class, 'attach']);
        Route::post('detach', [FileController::class, 'detach']);
        Route::get('/', [FileController::class, 'index']);
        Route::get('{id}', [FileController::class, 'show']);
        Route::delete('{id}', [FileController::class, 'destroy']);
        Route::get('{id}/download', [FileController::class, 'download']);

    });
    Route::get('/files/by-case/{caseId}', [FileController::class, 'getFilesByCase']);
    Route::get('/files/by-minute/{minuteId}', [FileController::class, 'getFilesByMinute']);
    Route::get('/files/by-task/{taskId}', [FileController::class, 'getFilesByTask']);

    // Case routes  
    Route::get('cases/all-sessions', [CaseController::class, 'allSessions']);
    Route::apiResource('cases', CaseController::class);
    Route::post('cases/{id}/archive', [CaseController::class, 'archive']);
    Route::post('cases/{id}/unarchive', [CaseController::class, 'unarchive']);
    Route::post('cases/{id}/attach-files', [CaseController::class, 'attachFiles']);

    // // Task routes
    Route::apiResource('tasks', TaskController::class);
    Route::post('tasks/{id}/archive', [TaskController::class, 'archive']);
    Route::post('tasks/{id}/unarchive', [TaskController::class, 'unarchive']);
    Route::post('tasks/{id}/attach-files', [TaskController::class, 'attachFiles']);

    // Minute routes
    Route::apiResource('minutes', MinuteController::class);
    Route::post('minutes/{id}/archive', [MinuteController::class, 'archive']);
    Route::post('minutes/{id}/unarchive', [MinuteController::class, 'unarchive']);
    Route::post('minutes/{id}/attach-files', [MinuteController::class, 'attachFiles']);
    Route::get('cases/{id}/minutes', [MinuteController::class, 'getCaseMinutes']);
    Route::get('/minutes/client/{clientId}/grouped', [MinuteController::class, 'getClientMinutesGrouped']);

    // Case Sub-resources (Sessions, Notes, Expenses, Fees)
    Route::prefix('cases/{caseId}')->group(function () {
        Route::get('sessions', [\App\Http\Controllers\Api\CaseSessionController::class, 'index']);
        Route::post('sessions', [\App\Http\Controllers\Api\CaseSessionController::class, 'store']);

        Route::get('notes', [\App\Http\Controllers\Api\CaseNoteController::class, 'index']);
        Route::post('notes', [\App\Http\Controllers\Api\CaseNoteController::class, 'store']);

        Route::get('expenses', [\App\Http\Controllers\Api\ExpenseController::class, 'index']);
        Route::post('expenses', [\App\Http\Controllers\Api\ExpenseController::class, 'store']);

        Route::get('fees', [\App\Http\Controllers\Api\FeeController::class, 'index']);
        Route::post('fees', [\App\Http\Controllers\Api\FeeController::class, 'store']);
    });

    // Individual Resource Management (Update/Delete)
    Route::match(['put', 'patch'], 'sessions/{id}', [\App\Http\Controllers\Api\CaseSessionController::class, 'update']);
    Route::post('sessions/{id}/postpone', [\App\Http\Controllers\Api\CaseSessionController::class, 'postpone']);
    Route::post('sessions/{id}/archive', [\App\Http\Controllers\Api\CaseSessionController::class, 'archive']);
    Route::post('sessions/{id}/unarchive', [\App\Http\Controllers\Api\CaseSessionController::class, 'unarchive']);
    Route::delete('sessions/{id}', [\App\Http\Controllers\Api\CaseSessionController::class, 'destroy']);
    Route::delete('notes/{id}', [\App\Http\Controllers\Api\CaseNoteController::class, 'destroy']);
    Route::delete('expenses/{id}', [\App\Http\Controllers\Api\ExpenseController::class, 'destroy']);
    Route::delete('fees/{id}', [\App\Http\Controllers\Api\FeeController::class, 'destroy']);

    // Client routes
    // Route::apiResource('clients', ClientController::class);

    // Team management (Admins only)
    Route::get('team', [\App\Http\Controllers\Api\TeamController::class, 'index']);
    Route::post('team', [\App\Http\Controllers\Api\TeamController::class, 'store']);
    Route::put('team/{id}', [\App\Http\Controllers\Api\TeamController::class, 'update']);  // <-- إضافة تعديل معلومات العضو
    Route::put('team/{id}/change-password', [\App\Http\Controllers\Api\TeamController::class, 'changePassword']);  // <-- إضافة تغيير كلمة المرور
    Route::delete('team/{id}', [\App\Http\Controllers\Api\TeamController::class, 'destroy']);

    // Office Routes
    Route::prefix('offices')->group(function () {
        Route::get('/', [OfficeController::class, 'index']);
        Route::post('/', [OfficeController::class, 'store']);
        Route::get('/{id}', [OfficeController::class, 'show']);
        Route::match(['put', 'patch'], '/{id}', [OfficeController::class, 'update']);
        Route::delete('/{id}', [OfficeController::class, 'destroy']);

        // علاقات إضافية
        Route::get('/{id}/users', [OfficeController::class, 'users']);
        Route::get('/{id}/cases', [OfficeController::class, 'cases']);
    });
});