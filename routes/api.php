<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TodoApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

// Authentication
Route::post('/register', [AuthController::class, 'register'])->name('api.register');
Route::post('/login', [AuthController::class, 'login'])->name('api.login');

// Protected Todo Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    Route::get('/todos', [TodoApiController::class, 'index'])->name('api.todos.index');
    Route::post('/todos', [TodoApiController::class, 'store'])->name('api.todos.store');
    Route::get('/todos/{id}', [TodoApiController::class, 'show'])->name('api.todos.show');
    Route::put('/todos/{id}', [TodoApiController::class, 'update'])->name('api.todos.update');
    Route::delete('/todos/{id}', [TodoApiController::class, 'destroy'])->name('api.todos.destroy');
});
