<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\SignupController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('proxy', [HomeController::class, 'proxy'])->name('proxy');
Route::get('logout', [UserController::class, 'logout'])->name('logout');

Route::get('/', [HomeController::class, 'home'])->name('home');

Route::middleware('auth')->group(function () {
    Route::get('signup', [SignupController::class, 'show'])->name('login.signup');
    Route::post('signup', [SignupController::class, 'store'])->name('login.signup.store');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [UserController::class, 'login'])->name('login');
    Route::get('login/{socialprovider:code}', [UserController::class, 'login_redirect'])->name('login.redirect');
    Route::get('login/{socialprovider:code}/return', [UserController::class, 'login_return'])->name('login.return');
});
