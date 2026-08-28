<?php

use App\Http\Controllers\ContentController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\TodoController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('scenecraft', 'scenecraft')->name('scenecraft');
    Route::view('content', 'content')->name('content');
    Route::post('/content/analyze', [ContentController::class, 'analyze'])->name('content.analyze');
    Route::get('/conversation', [ConversationController::class, 'history'])->name('conversation');

    Route::get('/todo/index', [TodoController::class, 'index'])->name('todo.index');
    Route::post('/todo/create', [TodoController::class, 'create'])->name('todo.create');
    Route::post('/todo/update', [TodoController::class, 'update'])->name('todo.update');
    Route::post('/todo/delete', [TodoController::class, 'delete'])->name('todo.delete');
});

require __DIR__ . '/settings.php';
