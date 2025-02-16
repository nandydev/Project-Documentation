<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ProjectController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});



// Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Project routes
    Route::apiResource('projects', ProjectController::class);
    Route::post('/projects/update/{id}', [ProjectController::class, 'update']);

    Route::post('/projects/{project}/generate-descriptions', [ProjectController::class, 'generateDescriptions']);
    Route::put('/projects/{project}/edit-descriptions', [ProjectController::class, 'editDescriptions']);

    // AI-Generated Overall Description Endpoint
    Route::post('projects/{id}/generate-overall-description', [ProjectController::class, 'generateOverallDescription']);

    // API Documentation Endpoints
    Route::post('/projects/{id}/documentation/generate', [ProjectController::class, 'generateApiDocumentation']);
    Route::get('/projects/{id}/documentation/preview', [ProjectController::class, 'previewApiDocumentation']);
    
});

