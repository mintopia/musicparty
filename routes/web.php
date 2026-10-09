<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IntegrationTokenController as AdminIntegrationTokenController;
use App\Http\Controllers\Admin\PartyController as AdminPartyController;
use App\Http\Controllers\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\ThemeController as AdminThemeController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ColourSchemeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('proxy', [HomeController::class, 'proxy'])->name('proxy');
Route::get('logout', [UserController::class, 'logout'])->name('logout');

Route::put('colour-scheme', [ColourSchemeController::class, 'update'])->middleware('auth')->name('colour-scheme.update');

Route::get('/', [HomeController::class, 'home'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [UserController::class, 'login'])->name('login');
    Route::get('login/{socialprovider:code}', [UserController::class, 'login_redirect'])->name('login.redirect');
    Route::get('login/{socialprovider:code}/return', [UserController::class, 'login_return'])->name('login.return');
});

Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
    Route::delete('users/{user}/suspend', [AdminUserController::class, 'unsuspend'])->name('users.unsuspend');
    Route::post('users/{user}/roles', [AdminUserController::class, 'grantRole'])->name('users.roles.grant');
    Route::delete('users/{user}/roles/{role}', [AdminUserController::class, 'revokeRole'])->name('users.roles.revoke');
    Route::get('tokens', [AdminIntegrationTokenController::class, 'index'])->name('tokens.index');
    Route::post('tokens', [AdminIntegrationTokenController::class, 'store'])->name('tokens.store');
    Route::delete('tokens/{token}', [AdminIntegrationTokenController::class, 'destroy'])->name('tokens.destroy');
    Route::get('providers', [AdminProviderController::class, 'index'])->name('providers.index');
    Route::put('providers/{provider}', [AdminProviderController::class, 'update'])->name('providers.update');
    Route::get('settings', [AdminSettingsController::class, 'show'])->name('settings.show');
    Route::post('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::get('parties', [AdminPartyController::class, 'index'])->name('parties.index');
    Route::post('parties/{party:id}/act-as-host', [AdminPartyController::class, 'enter'])->name('parties.act-as-host.enter');
    Route::delete('parties/{party:id}/act-as-host', [AdminPartyController::class, 'leave'])->name('parties.act-as-host.leave');
    Route::get('theme', [AdminThemeController::class, 'show'])->name('theme.show');
    Route::put('theme', [AdminThemeController::class, 'update'])->name('theme.update');
    Route::delete('theme', [AdminThemeController::class, 'destroy'])->name('theme.destroy');
});
