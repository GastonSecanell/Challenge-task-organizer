<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\ColumnController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\CardChecklistItemController;
use App\Http\Controllers\Api\CardAttachmentController;
use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\CardLabelController;
use App\Http\Controllers\Api\BoardMemberController;
use App\Http\Controllers\Api\CardMemberController;
use App\Http\Controllers\Api\UserAvatarController;
use App\Http\Controllers\Api\CardCommentController;
use App\Http\Controllers\Api\CardActivityController;
use App\Http\Controllers\Api\RoleController;


use App\Http\Controllers\Api\EtiquetaController;
use App\Http\Controllers\Api\PrioridadController;
use App\Http\Controllers\Api\TareaController;
use App\Http\Controllers\Api\UserController;

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




    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me/change-password', [AuthController::class, 'changePassword']);

    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update']);

    Route::get('/audit', [AuditController::class, 'index']);

    Route::get('/boards', [BoardController::class, 'index']);
    Route::post('/boards', [BoardController::class, 'store']);
    Route::patch('/boards/{board}', [BoardController::class, 'update']);
    Route::patch('/boards/{board}/archive', [BoardController::class, 'archive']);
    Route::patch('/boards/{board}/unarchive', [BoardController::class, 'unarchive']);
    Route::put('/boards/{board}/favorite', [BoardController::class, 'favorite']);
    Route::delete('/boards/{board}', [BoardController::class, 'destroy']);
    Route::get('/boards/{board}', [BoardController::class, 'show']);
    Route::post('/boards/{board}/transfer-owner', [BoardController::class, 'transferOwner']);
    Route::get('/boards/{board}/labels', [LabelController::class, 'index']);
    Route::post('/boards/{board}/labels', [LabelController::class, 'store']);
    Route::get('/boards/{board}/members', [BoardMemberController::class, 'index']);
    Route::get('/boards/{board}/member-options', [BoardMemberController::class, 'options']);
    Route::post('/boards/{board}/members', [BoardMemberController::class, 'store']);
    Route::delete('/boards/{board}/members/{user}', [BoardMemberController::class, 'destroy']);

    Route::get('/users/{user}/avatar', [UserAvatarController::class, 'show']);
    Route::post('/users/{user}/avatar', [UserAvatarController::class, 'store']);
    Route::delete('/users/{user}/avatar', [UserAvatarController::class, 'destroy']);

    Route::patch('/labels/{label}', [LabelController::class, 'update']);
    Route::delete('/labels/{label}', [LabelController::class, 'destroy']);

    Route::post('/columns', [ColumnController::class, 'store']);
    Route::patch('/columns/{column}', [ColumnController::class, 'update']);
    Route::delete('/columns/{column}', [ColumnController::class, 'destroy']);

    Route::get('/cards/{card}', [CardController::class, 'show']);
    Route::put('/cards/{card}/labels', [CardLabelController::class, 'update']);
    Route::put('/cards/{card}/members', [CardMemberController::class, 'update']);
    Route::get('/cards/{card}/comments', [CardCommentController::class, 'index']);
    Route::post('/cards/{card}/comments', [CardCommentController::class, 'store']);
    Route::delete('/cards/{card}/comments/{comment}', [CardCommentController::class, 'destroy']);
    Route::get('/cards/{card}/activity', [CardActivityController::class, 'index']);
    Route::patch('/cards/{card}/move', [CardController::class, 'move']);
    Route::post('/cards', [CardController::class, 'store']);
    Route::patch('/cards/{card}', [CardController::class, 'update']);
    Route::delete('/cards/{card}', [CardController::class, 'destroy']);

    Route::post('/cards/{card}/checklist-items', [CardChecklistItemController::class, 'store']);
    Route::patch('/checklist-items/{item}', [CardChecklistItemController::class, 'update']);
    Route::delete('/checklist-items/{item}', [CardChecklistItemController::class, 'destroy']);

    Route::get('/cards/{card}/attachments', [CardAttachmentController::class, 'index']);
    Route::post('/cards/{card}/attachments', [CardAttachmentController::class, 'store']);
    Route::get('/attachments/{attachment}/thumb', [CardAttachmentController::class, 'thumb']);
    Route::get('/attachments/{attachment}/preview', [CardAttachmentController::class, 'preview']);
    Route::get('/attachments/{attachment}/download', [CardAttachmentController::class, 'download']);
    Route::delete('/attachments/{attachment}', [CardAttachmentController::class, 'destroy']);
});
