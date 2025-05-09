<?php

use App\Http\Controllers\Webhooks\PartyController;
use Illuminate\Support\Facades\Route;

Route::name('webhooks.')->middleware('auth:sanctum')->group(function () {
    Route::post('parties/{party}/librespot', [PartyController::class, 'librespot'])->middleware('can:update,party')->name('parties.update');
    Route::post('parties/{party}/simple', [PartyController::class, 'simple'])->middleware('can:update,party')->name('parties.simple');
});
