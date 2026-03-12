<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EtiquetaController;
use App\Http\Controllers\Api\PrioridadController;
use App\Http\Controllers\Api\TareaController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;

Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('tareas', TareaController::class);
    Route::patch('tareas/{tarea}/estado', [TareaController::class, 'cambiarEstado']);
    Route::patch('/tareas/{tarea}/etiquetas', [TareaController::class, 'syncEtiquetas']);
    Route::patch('/tareas/{tarea}/prioridad', [TareaController::class, 'cambiarPrioridad']);

    Route::get('prioridades', [PrioridadController::class, 'index']);
    Route::get('etiquetas', [EtiquetaController::class, 'index']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::post('users', [UserController::class, 'store']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);
    Route::get('/roles', [RoleController::class, 'index']);
});
