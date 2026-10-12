<?php

use App\Http\Controllers\Webhooks\PartyController;
use App\Http\Middleware\ThrottleWebhookPerParty;
use Illuminate\Support\Facades\Route;

Route::name('webhooks.')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('parties/{party}/librespot', [PartyController::class, 'librespot'])->middleware('can:update,party')->name('parties.update');
        Route::post('parties/{party}/simple', [PartyController::class, 'simple'])->middleware('can:update,party')->name('parties.simple');
    });
    Route::post('parties/{party}/soloist', [PartyController::class, 'soloist'])->middleware(ThrottleWebhookPerParty::class)->name('parties.soloist');
});
