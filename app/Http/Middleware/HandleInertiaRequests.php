<?php

namespace App\Http\Middleware;

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
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->nickname,
                    'avatarUrl' => $request->user()->avatarUrl(),
                    'email' => $request->user()->getEmail(),
                ] : null,
            ],
            'parties' => fn (): array => $request->user()
                ? $request->user()->memberParties->map(fn ($party): array => [
                    'code' => $party->code,
                    'name' => $party->name,
                ])->values()->all()
                : [],
        ];
    }
}
