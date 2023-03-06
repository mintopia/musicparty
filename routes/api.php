<?php

use App\Http\Controllers\Api\V1\PartyController;
use App\Http\Controllers\Api\V1\TrackController;
use App\Http\Controllers\Api\V1\UpcomingSongController;
use App\Http\Controllers\Api\V1\VoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->name('api.v1.')->middleware('treblle')->group(function() {
    Route::get('ping', function() {
        return response()->noContent();
    })->name('ping');

    Route::middleware('auth:sanctum')->group(function() {
        Route::apiResource('parties', PartyController::class);
        Route::apiResource('parties.upcoming', UpcomingSongController::class, [
            'only' => ['index', 'store', 'show', 'destroy'],
        ]);
        Route::post('parties/{party}/upcoming/{upcoming}/vote', [VoteController::class, 'store'])->name('parties.upcoming.vote');
        Route::get('parties/{party}/search', [TrackController::class, 'index'])->name('parties.search');
    });
});
