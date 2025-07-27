<?php

use App\Http\Controllers\Api\FolderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TagController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Rotas para Links
    Route::get('/links', [LinkController::class, 'index']);
    Route::post('/links', [LinkController::class, 'store']);
    Route::put('/links/{link}', [LinkController::class, 'update']);
    Route::delete('/links/{link}', [LinkController::class, 'destroy']);

    // Rotas para Tags
    Route::get('/tags', [TagController::class, 'index']);

    //Rotas para Pastas
    Route::apiResource('folders', FolderController::class);

    // Rota de pesquisa unificada
    Route::get('/search', [SearchController::class, 'index']);
});
