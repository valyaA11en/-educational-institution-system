<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;

// Главная страница
Route::get('/', function () {
    return redirect('/login');
});

// Авторизация
Route::get('/login', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }
    
    return view('auth.login');
})->name('login');

Route::post('/login', [AuthController::class, 'webLogin'])->name('login.post');

Route::post('/logout', function () {
    auth()->logout();
    return redirect('/login');
})->name('logout');

// Дашборд (требует авторизации)
Route::get('/dashboard', function () {
    $user = auth()->user();
    return view('dashboard', compact('user'));
})->middleware('auth')->name('dashboard');

// API Testing page (development only)
Route::get('/api-test', function () {
    return view('api-test');
})->name('api-test');
