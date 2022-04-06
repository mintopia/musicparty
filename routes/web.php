<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\UpcomingSongController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/auth/discord', [AuthController::class, 'login'])->name('login');
Route::get('/auth/discord/redirect', [AuthController::class, 'login_redirect'])->name('login.redirect');

Route::middleware('auth')->group(function() {
    Route::get('/auth/spotify', [AuthController::class, 'spotify_link'])->name('auth.spotify_link');
    Route::get('/auth/spotify/redirect', [AuthController::class, 'spotify_redirect'])->name('auth.spotify_redirect');

    Route::post('/parties/join', [PartyController::class, 'join'])->name('parties.join');

    Route::get('/parties/{party}', [PartyController::class, 'show'])->name('parties.show');
    Route::get('/parties/{party}/tv', [PartyController::class, 'tv'])->name('parties.tv');
    Route::get('/parties/{party}/search', [UpcomingSongController::class, 'search'])->name('parties.upcoming.search');
    Route::get('/parties/{party}/vote/{id}', [UpcomingSongController::class, 'vote'])->name('parties.upcoming.vote');

    // Routes that require spotify
    Route::middleware('can:create,App\Models\Party')->group(function() {
        Route::resource('/parties', PartyController::class, [
            'except' => ['show', 'list'],
        ]);
        Route::get('/parties/{party}/upcoming/{upcomingsong}/delete', [UpcomingSongController::class, 'delete'])->name('parties.upcoming.delete');
    });
});
