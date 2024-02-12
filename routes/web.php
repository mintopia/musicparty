<?php

use App\Http\Controllers\Admin\ClanController as AdminClanController;
use App\Http\Controllers\Admin\ClanMembershipController as AdminClanMembershipController;
use App\Http\Controllers\Admin\EmailAddressController as AdminEmailAddressController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\EventMappingController;
use App\Http\Controllers\Admin\HomeController as AdminHomeController;
use App\Http\Controllers\Admin\LinkedAccountController as AdminLinkedAccountController;
use App\Http\Controllers\Admin\SeatController as AdminSeatController;
use App\Http\Controllers\Admin\SeatingPlanController as AdminSeatingPlanController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\SocialProviderController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\TicketProviderController;
use App\Http\Controllers\Admin\TicketTypeController;
use App\Http\Controllers\Admin\TicketTypeMappingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ClanController;
use App\Http\Controllers\ClanMembershipController;
use App\Http\Controllers\EmailAddressController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LinkedAccountController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PartyMemberController;
use App\Http\Controllers\PlayedSongController;
use App\Http\Controllers\SeatingPlanController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UpcomingSongController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoteController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\RedirectOnFirstLoginMiddleware;
use Illuminate\Support\Facades\Route;


// Always available
Route::get('logout', [UserController::class, 'logout'])->name('logout');

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    Route::get('login/signup', [UserController::class, 'signup'])->name('login.signup');
    Route::match(['PUT', 'PATCH'], 'login/signup', [UserController::class, 'signup_process'])->name('login.signup.process');

    Route::middleware(RedirectOnFirstLoginMiddleware::class)->group(function () {

        Route::get('/', [HomeController::class, 'home'])->name('home');

        Route::get('/profile', [UserController::class, 'profile'])->name('user.profile');
        Route::get('/profile/edit', [UserController::class, 'edit'])->name('user.profile.edit');
        Route::get('/spotify', [UserController::class, 'spotify'])->name('user.spotify');
        Route::match(['PATCH', 'PUT'], '/profile', [UserController::class, 'update'])->name('user.profile.update');

        Route::resource('parties', PartyController::class)->except(['destroy']);
        Route::middleware('can:update,party')->group(function() {
            Route::resource('parties.users', PartyMemberController::class)->only(['index', 'show', 'edit', 'update'])->scoped();
            Route::resource('parties.songs', UpcomingSongController::class)->only(['index', 'show', 'destroy'])->scoped();

        });
        Route::get('parties/{party}/search', [PartyController::class, 'search'])->name('parties.search');

        Route::get('/profile/accounts/{socialprovider:code}/link', [LinkedAccountController::class, 'create'])->name('linkedaccounts.create');
        Route::get('/profile/accounts/{socialprovider:code}/return', [LinkedAccountController::class, 'store'])->name('linkedaccounts.store');

        Route::middleware('can:update,linkedaccount')->group(function () {
            Route::get('/profile/accounts/{linkedaccount}/delete', [LinkedAccountController::class, 'store'])->name('linkedaccounts.delete');
            Route::delete('/profile/accounts/{linkedaccount}', [LinkedAccountController::class, 'store'])->name('linkedaccounts.destroy');
        });

        Route::get('/admin/unimpersonate', [AdminHomeController::class, 'unimpersonate'])->name('admin.unimpersonate');

        Route::middleware('can:admin')->name('admin.')->prefix('admin')->group(function () {
            Route::get('/', [AdminHomeController::class, 'dashboard'])->name('dashboard');

            Route::resource('users', AdminUserController::class);
            Route::get('users/{user}/delete', [AdminUserController::class, 'delete'])->name('users.delete');
            Route::get('users/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('users.impersonate');
            Route::get('users/{user}/sync', [AdminUserController::class, 'sync_tickets'])->name('users.sync');

            Route::get('users/{user}/accounts/{account}', [AdminLinkedAccountController::class, 'delete'])->name('users.accounts.delete')->scopeBindings();
            Route::delete('users/{user}/accounts/{account}', [AdminLinkedAccountController::class, 'destroy'])->name('users.accounts.destroy')->scopeBindings();

            Route::get('settings', [AdminSettingController::class, 'index'])->name('settings.index');
            Route::match(['PUT', 'PATCH'], 'settings', [AdminSettingController::class, 'update'])->name('settings.update');
            Route::get('settings/socialproviders/{provider}/edit', [SocialProviderController::class, 'edit'])->name('settings.socialproviders.edit');
            Route::match(['PUT', 'PATCH'], 'settings/socialproviders/{provider}', [SocialProviderController::class, 'update'])->name('settings.socialproviders.update');

            Route::prefix('settings')->name('settings.')->group(function() {
                Route::resource('themes', ThemeController::class)->except(['index', 'show']);
                Route::get('themes/{theme}/delete', [ThemeController::class, 'delete'])->name('themes.delete');
            });
        });
    });
});

// Guest-only routes
Route::middleware('guest')->group(function () {
    Route::get('login', [UserController::class, 'login'])->name('login');
    Route::get('login/{socialprovider:code}', [UserController::class, 'login_redirect'])->name('login.redirect');
    Route::get('login/{socialprovider:code}/return', [UserController::class, 'login_return'])->name('login.return');
});
