<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PartyTvController;
use App\Http\Controllers\SignupController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('proxy', [HomeController::class, 'proxy'])->name('proxy');
Route::get('logout', [UserController::class, 'logout'])->name('logout');

Route::get('/', [HomeController::class, 'home'])->name('home');

Route::get('parties/{party}/tv', [PartyTvController::class, 'show'])
    ->where('party', '[A-Za-z]{4}')
    ->name('parties.tv');

Route::middleware('auth')->group(function () {
    Route::get('signup', [SignupController::class, 'show'])->name('login.signup');
    Route::post('signup', [SignupController::class, 'store'])->name('login.signup.store');

    Route::get('parties/create', [PartyController::class, 'create'])->name('parties.create');
    Route::post('parties', [PartyController::class, 'store'])->name('parties.store');
    Route::post('parties/join', [PartyController::class, 'join'])->name('parties.join');
    Route::get('parties/{party}/log', [PartyController::class, 'log'])
        ->where('party', '[A-Za-z]{4}')
        ->name('parties.log');
    Route::patch('parties/{party}', [PartyController::class, 'update'])
        ->where('party', '[A-Za-z]{4}')
        ->name('parties.update');
    Route::post('parties/{party}/requests', [PartyController::class, 'storeRequest'])
        ->where('party', '[A-Za-z]{4}')
        ->name('parties.requests.store');
    foreach (['live', 'pause', 'end', 'reopen'] as $transition) {
        Route::post("parties/{party}/{$transition}", [PartyController::class, $transition])
            ->where('party', '[A-Za-z]{4}')
            ->name("parties.{$transition}");
    }
    Route::put('parties/{party}/requests/{trackRequest}/vote', [PartyController::class, 'storeVote'])
        ->where('party', '[A-Za-z]{4}')
        ->whereNumber('trackRequest')
        ->name('parties.requests.vote.store');
    Route::delete('parties/{party}/requests/{trackRequest}/vote', [PartyController::class, 'destroyVote'])
        ->where('party', '[A-Za-z]{4}')
        ->whereNumber('trackRequest')
        ->name('parties.requests.vote.destroy');
    Route::put('parties/{party}/requests/{trackRequest}/rating', [PartyController::class, 'storeNowPlayingRating'])
        ->where('party', '[A-Za-z]{4}')
        ->whereNumber('trackRequest')
        ->name('parties.requests.rating.store');
    Route::delete('parties/{party}/requests/{trackRequest}/rating', [PartyController::class, 'destroyNowPlayingRating'])
        ->where('party', '[A-Za-z]{4}')
        ->whereNumber('trackRequest')
        ->name('parties.requests.rating.destroy');
    Route::put('parties/{party}/plays/{play}/rating', [PartyController::class, 'storeRating'])
        ->where('party', '[A-Za-z]{4}')
        ->whereNumber('play')
        ->name('parties.plays.rating.store');
    Route::delete('parties/{party}/plays/{play}/rating', [PartyController::class, 'destroyRating'])
        ->where('party', '[A-Za-z]{4}')
        ->whereNumber('play')
        ->name('parties.plays.rating.destroy');
    Route::get('parties/{party}/{section?}', [PartyController::class, 'show'])
        ->where('party', '[A-Za-z]{4}')
        ->where('section', 'queue|search|history|party')
        ->name('parties.show');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [UserController::class, 'login'])->name('login');
    Route::get('login/{socialprovider:code}', [UserController::class, 'login_redirect'])->name('login.redirect');
    Route::get('login/{socialprovider:code}/return', [UserController::class, 'login_return'])->name('login.return');
});
