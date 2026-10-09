<?php

namespace App\Http\Middleware;

use App\Domain\Admin\SiteSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $site = app(SiteSettings::class);

        return [
            ...parent::share($request),
            'appName' => $site->name(),
            'site' => $site->toArray(),
            'colourScheme' => $request->user()?->colour_scheme?->value,
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->nickname,
                    'avatarUrl' => $request->user()->avatarUrl(),
                    'email' => $request->user()->getEmail(),
                    'is_admin' => $request->user()->hasRole('admin'),
                ] : null,
            ],
            'parties' => fn (): array => $request->user()
                ? $request->user()->parties->map(fn ($party): array => [
                    'code' => $party->code,
                    'name' => $party->name,
                ])->values()->all()
                : [],
        ];
    }
}
