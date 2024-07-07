<?php

use App\Http\Controllers\Api\V1\PartyController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\SongRatingController;
use App\Http\Controllers\Api\V1\UpcomingSongController;
use App\Http\Controllers\Api\V1\VoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('ping', [PingController::class, 'index'])->name('ping');
    Route::apiResource('parties', PartyController::class)->only(['show']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('parties', PartyController::class)->only(['update']);
        Route::post('parties/{party}/control', [PartyController::class, 'control'])->name('parties.control');
        Route::apiResource('parties.upcomingsongs', UpcomingSongController::class)->scoped();
        Route::apiResource('parties.upcomingsongs.vote', VoteController::class)->only(['store'])->scoped();
        Route::apiResource('parties.playedsongs.rate', SongRatingController::class)->only(['store'])->scoped();
    });
});
