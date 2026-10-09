<?php

use App\Http\Controllers\Api\V1\Admin\IntegrationTokenController as AdminIntegrationTokenController;
use App\Http\Controllers\Api\V1\Admin\PartyController as AdminPartyController;
use App\Http\Controllers\Api\V1\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Api\V1\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\V1\Admin\ThemeController as AdminThemeController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\ColourSchemeController;
use App\Http\Controllers\Api\V1\IntegrationPingController;
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
    Route::get('integration/ping', [IntegrationPingController::class, 'index'])->middleware(['auth:integration', 'integration.ability:read'])->name('integration.ping');
    Route::middleware('auth:sanctum')->group(function () {
        Route::put('me/colour-scheme', [ColourSchemeController::class, 'update'])->name('me.colour-scheme');
        Route::apiResource('parties', PartyController::class)->only(['update']);
        Route::post('parties/{party}/control', [PartyController::class, 'control'])->name('parties.control');
        Route::apiResource('parties.upcomingsongs', UpcomingSongController::class)->scoped();
        Route::apiResource('parties.upcomingsongs.vote', VoteController::class)->only(['store'])->scoped();
        Route::apiResource('parties.playedsongs.rate', SongRatingController::class)->only(['store'])->scoped();
    });
    Route::middleware(['auth:sanctum', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('tokens', [AdminIntegrationTokenController::class, 'index'])->name('tokens.index');
        Route::post('tokens', [AdminIntegrationTokenController::class, 'store'])->name('tokens.store');
        Route::delete('tokens/{token}', [AdminIntegrationTokenController::class, 'destroy'])->name('tokens.destroy');
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('users/{user}/suspension', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::delete('users/{user}/suspension', [AdminUserController::class, 'unsuspend'])->name('users.unsuspend');
        Route::post('users/{user}/roles', [AdminUserController::class, 'grantRole'])->name('users.roles.grant');
        Route::delete('users/{user}/roles/{role}', [AdminUserController::class, 'revokeRole'])->name('users.roles.revoke');
        Route::get('providers', [AdminProviderController::class, 'index'])->name('providers.index');
        Route::put('providers/{provider}', [AdminProviderController::class, 'update'])->name('providers.update');
        Route::get('settings', [AdminSettingsController::class, 'show'])->name('settings.show');
        Route::post('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::post('parties/{party:id}/act-as-host', [AdminPartyController::class, 'enter'])->name('parties.act-as-host.enter');
        Route::delete('parties/{party:id}/act-as-host', [AdminPartyController::class, 'leave'])->name('parties.act-as-host.leave');
        Route::get('theme', [AdminThemeController::class, 'show'])->name('theme.show');
        Route::put('theme', [AdminThemeController::class, 'update'])->name('theme.update');
        Route::delete('theme', [AdminThemeController::class, 'destroy'])->name('theme.destroy');
    });
});
