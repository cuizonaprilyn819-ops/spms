<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

require __DIR__.'/spms.php';
require __DIR__.'/auth.php';

Route::post('/assistant/chat', \App\Http\Controllers\AiChatController::class)
    ->middleware(['auth', 'throttle:10,1'])->name('assistant.chat');

// The browser API deliberately shares session authentication and CSRF protection.
Route::prefix('api')->name('api.')->middleware(\App\Http\Middleware\ApiResponse::class)
    ->group(__DIR__.'/spms.php');
