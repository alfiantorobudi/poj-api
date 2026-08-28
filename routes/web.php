<?php

use App\Http\Controllers\ContentController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('scenecraft', 'scenecraft')->name('scenecraft');
    Route::view('content', 'content')->name('content');
    Route::post('/content/analyze', [ContentController::class, 'analyze'])->name('content.analyze');
    Route::view('conversation', 'conversation')->name('conversation');

    Route::view('todos', 'todo')->name('todos.index');
    Route::view('todo', 'todo')->name('todo');
});

require __DIR__.'/settings.php';
