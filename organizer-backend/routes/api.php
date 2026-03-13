<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EtiquetaController;
use App\Http\Controllers\Api\PrioridadController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TareaController;
use App\Http\Controllers\Api\UserController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('tareas', TareaController::class);
    Route::apiResource('users', UserController::class);

    Route::prefix('tareas/{tarea}')->group(function () {
        Route::patch('estado', [TareaController::class, 'cambiarEstado']);
        Route::patch('etiquetas', [TareaController::class, 'syncEtiquetas']);
        Route::patch('prioridad', [TareaController::class, 'cambiarPrioridad']);
    });

    Route::get('prioridades', [PrioridadController::class, 'index']);
    Route::get('etiquetas', [EtiquetaController::class, 'index']);
    Route::get('roles', [RoleController::class, 'index']);
});
