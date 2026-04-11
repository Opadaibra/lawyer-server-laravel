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
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::get('me', [AuthController::class, 'me'])->middleware('auth:api');

// Protected routes
Route::middleware('auth:api')->group(function () {


    // ===== CLIENTS =====
    Route::apiResource('clients', ClientController::class);
    Route::get('clients/search/{query}', [ClientController::class, 'search']);
    Route::get('clients/{id}/cases', [ClientController::class, 'cases']);
    Route::get('client-portal/cases', [ClientController::class, 'portalCases']);



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
    Route::delete('sessions/{id}', [\App\Http\Controllers\Api\CaseSessionController::class, 'destroy']);
    Route::delete('notes/{id}', [\App\Http\Controllers\Api\CaseNoteController::class, 'destroy']);
    Route::delete('expenses/{id}', [\App\Http\Controllers\Api\ExpenseController::class, 'destroy']);
    Route::delete('fees/{id}', [\App\Http\Controllers\Api\FeeController::class, 'destroy']);

    // Client routes
    // Route::apiResource('clients', ClientController::class);

    // Team management (Admins only)
    Route::get('team', [\App\Http\Controllers\Api\TeamController::class, 'index']);
    Route::post('team', [\App\Http\Controllers\Api\TeamController::class, 'store']);
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