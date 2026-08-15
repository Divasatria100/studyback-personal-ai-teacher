<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Materials\MaterialController;
use App\Http\Controllers\Api\Materials\TopicController;
use App\Http\Controllers\Api\Quizzes\QuizController;
use App\Http\Controllers\Api\StudySessions\StudySessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Studyback REST API surface (API Design §21). Every route except register and
| login is authenticated via Sanctum; ownership is enforced by scoping each
| lookup to the current user, returning 404 for foreign resources.
|
*/

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('materials', MaterialController::class)->only(['index', 'show', 'store']);

    Route::get('materials/{material}/download', [MaterialController::class, 'download']);
    Route::get('materials/{material}/topics', [TopicController::class, 'index']);

    Route::post('materials/{material}/study-sessions', [StudySessionController::class, 'store']);

    Route::get('study-sessions/{studySession}', [StudySessionController::class, 'show']);
    Route::patch('study-sessions/{studySession}/complete', [StudySessionController::class, 'complete']);
    Route::post('study-sessions/{studySession}/explanations', [StudySessionController::class, 'explanations']);
    Route::post('study-sessions/{studySession}/quizzes', [StudySessionController::class, 'storeQuiz']);

    Route::get('quizzes/{quiz}', [QuizController::class, 'show']);
    Route::post('quizzes/{quiz}/questions/{quizQuestion}/answer', [QuizController::class, 'answer']);
});
