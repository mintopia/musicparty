<?php

namespace App\Providers;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->hasRole('admin'));
        Gate::define('viewApiDocs', fn (User $user) => $user->hasRole('admin'));
        Gate::define('viewPulse', fn (User $user) => $user->hasRole('admin'));
    }
}
