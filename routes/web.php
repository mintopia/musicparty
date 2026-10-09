<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\SpotifyLinkController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('proxy', [HomeController::class, 'proxy'])->name('proxy');
Route::get('logout', [UserController::class, 'logout'])->name('logout');

Route::get('/', [HomeController::class, 'home'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [UserController::class, 'login'])->name('login');
    Route::get('login/{socialprovider:code}', [UserController::class, 'login_redirect'])->name('login.redirect');
    Route::get('login/{socialprovider:code}/return', [UserController::class, 'login_return'])->name('login.return');
});

Route::middleware('auth')->group(function () {
    Route::get('parties/{party}/spotify/link', [SpotifyLinkController::class, 'redirect'])->name('spotify.link');
    Route::get('spotify/link/return', [SpotifyLinkController::class, 'callback'])->name('spotify.link.return');
});
