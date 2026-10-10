<?php

use App\Http\Controllers\Api\V1\Admin\IntegrationTokenController as AdminIntegrationTokenController;
use App\Http\Controllers\Api\V1\Admin\PartyController as AdminPartyController;
use App\Http\Controllers\Api\V1\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Api\V1\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\V1\Admin\ThemeController as AdminThemeController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\ColourSchemeController;
use App\Http\Controllers\Api\V1\IntegrationPingController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PartyBlocklistController;
use App\Http\Controllers\Api\V1\PartyController;
use App\Http\Controllers\Api\V1\PartyMemberController;
use App\Http\Controllers\Api\V1\PartyModController;
use App\Http\Controllers\Api\V1\PartyPlayController;
use App\Http\Controllers\Api\V1\PartyRequestController;
use App\Http\Controllers\Api\V1\PartyThemeController;
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
    Route::get('parties/{party}/theme', [PartyThemeController::class, 'show'])->name('parties.theme.show');
    Route::get('integration/ping', [IntegrationPingController::class, 'index'])->middleware(['auth:integration', 'integration.ability:read'])->name('integration.ping');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [MeController::class, 'show'])->name('me');
        Route::put('me/colour-scheme', [ColourSchemeController::class, 'update'])->name('me.colour-scheme');
        Route::apiResource('parties', PartyController::class)->only(['store', 'update']);
        Route::post('parties/{party}/live', [PartyController::class, 'live'])->name('parties.live');
        Route::post('parties/{party}/pause', [PartyController::class, 'pause'])->name('parties.pause');
        Route::post('parties/{party}/end', [PartyController::class, 'end'])->name('parties.end');
        Route::post('parties/{party}/reopen', [PartyController::class, 'reopen'])->name('parties.reopen');
        Route::post('parties/{party}/join', [PartyController::class, 'join'])->name('parties.join');
        Route::get('parties/{party}/log', [PartyController::class, 'log'])->name('parties.log');
        Route::get('parties/{party}/members', [PartyMemberController::class, 'index'])->name('parties.members.index');
        Route::put('parties/{party}/members/{member}/role', [PartyMemberController::class, 'role'])->whereNumber('member')->name('parties.members.role');
        Route::put('parties/{party}/members/{member}/ban', [PartyMemberController::class, 'ban'])->whereNumber('member')->name('parties.members.ban');
        Route::delete('parties/{party}/members/{member}/ban', [PartyMemberController::class, 'unban'])->whereNumber('member')->name('parties.members.unban');
        Route::get('parties/{party}/blocklist', [PartyBlocklistController::class, 'index'])->name('parties.blocklist.index');
        Route::post('parties/{party}/blocklist', [PartyBlocklistController::class, 'store'])->name('parties.blocklist.store');
        Route::put('parties/{party}/blocklist/{entry}', [PartyBlocklistController::class, 'update'])->whereNumber('entry')->name('parties.blocklist.update');
        Route::delete('parties/{party}/blocklist/{entry}', [PartyBlocklistController::class, 'destroy'])->whereNumber('entry')->name('parties.blocklist.destroy');
        Route::get('parties/{party}/mods', [PartyModController::class, 'index'])->name('parties.mods.index');
        Route::put('parties/{party}/mods/{mod}', [PartyModController::class, 'enable'])->where('mod', '[a-z0-9_-]+')->name('parties.mods.enable');
        Route::delete('parties/{party}/mods/{mod}', [PartyModController::class, 'disable'])->where('mod', '[a-z0-9_-]+')->name('parties.mods.disable');
        Route::put('parties/{party}/mods/{mod}/settings', [PartyModController::class, 'update'])->where('mod', '[a-z0-9_-]+')->name('parties.mods.settings');
        Route::get('parties/{party}/search', [PartyRequestController::class, 'search'])->name('parties.search');
        Route::post('parties/{party}/requests', [PartyRequestController::class, 'store'])->name('parties.requests.store');
        Route::get('parties/{party}/requests/pending', [PartyRequestController::class, 'pending'])->name('parties.requests.pending');
        Route::post('parties/{party}/requests/{trackRequest}/approve', [PartyRequestController::class, 'approve'])->whereNumber('trackRequest')->name('parties.requests.approve');
        Route::post('parties/{party}/requests/{trackRequest}/reject', [PartyRequestController::class, 'reject'])->whereNumber('trackRequest')->name('parties.requests.reject');
        Route::delete('parties/{party}/requests/{trackRequest}', [PartyRequestController::class, 'destroy'])->whereNumber('trackRequest')->name('parties.requests.destroy');
        Route::put('parties/{party}/requests/{trackRequest}/vote', [PartyRequestController::class, 'vote'])->whereNumber('trackRequest')->name('parties.requests.vote.store');
        Route::delete('parties/{party}/requests/{trackRequest}/vote', [PartyRequestController::class, 'retractVote'])->whereNumber('trackRequest')->name('parties.requests.vote.destroy');
        Route::put('parties/{party}/requests/{trackRequest}/rating', [PartyRequestController::class, 'rate'])->whereNumber('trackRequest')->name('parties.requests.rating.store');
        Route::delete('parties/{party}/requests/{trackRequest}/rating', [PartyRequestController::class, 'retractRating'])->whereNumber('trackRequest')->name('parties.requests.rating.destroy');
        Route::get('parties/{party}/history', [PartyPlayController::class, 'history'])->name('parties.history');
        Route::put('parties/{party}/plays/{play}/rating', [PartyPlayController::class, 'rate'])->whereNumber('play')->name('parties.plays.rating.store');
        Route::delete('parties/{party}/plays/{play}/rating', [PartyPlayController::class, 'retract'])->whereNumber('play')->name('parties.plays.rating.destroy');
        Route::get('parties/{party}/queue', [PartyRequestController::class, 'queue'])->name('parties.queue');
        Route::post('parties/{party}/playback/{control}', [PartyController::class, 'playback'])->where('control', 'play|pause|skip|seek|volume')->name('parties.playback');
        Route::post('parties/{party}/control', [PartyController::class, 'control'])->name('parties.control');
        Route::put('parties/{party}/theme', [PartyThemeController::class, 'update'])->name('parties.theme.update');
        Route::post('parties/{party}/theme', [PartyThemeController::class, 'update'])->name('parties.theme.upload');
        Route::delete('parties/{party}/theme', [PartyThemeController::class, 'destroy'])->name('parties.theme.destroy');
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
