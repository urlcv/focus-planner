<?php

use Illuminate\Support\Facades\Route;
use URLCV\FocusPlanner\Http\Controllers\FocusPlannerController;

Route::prefix('fp-api')->middleware(['web'])->group(function () {
    Route::post('/session', [FocusPlannerController::class, 'startSession']);
    Route::post('/recover', [FocusPlannerController::class, 'sendRecovery']);
    Route::get('/session/{token}', [FocusPlannerController::class, 'getSession']);
    Route::put('/session/{token}/settings', [FocusPlannerController::class, 'updateSettings']);
    Route::post('/session/{token}/tasks', [FocusPlannerController::class, 'createTask']);
    Route::put('/session/{token}/tasks/{id}', [FocusPlannerController::class, 'updateTask']);
    Route::delete('/session/{token}/tasks/{id}', [FocusPlannerController::class, 'deleteTask']);
    Route::post('/session/{token}/tasks/reorder', [FocusPlannerController::class, 'reorderTasks']);
});
