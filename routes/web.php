<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::get('/', function () {
    return view('home');
});

Route::post('/chat', [ChatController::class, 'ask']);
