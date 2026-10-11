<?php

namespace App\Http\Middleware;

use App\Domain\Admin\SiteSettings;
use App\Domain\Party\Models\Party;
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
            'defaultParty' => fn (): ?string => ($code = $site->get('default_party')) !== null && Party::query()->where('code', $code)->exists() ? $code : null,
            'colourScheme' => $request->user()?->colour_scheme?->value,
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->nickname,
                    'avatarUrl' => $request->user()->avatarUrl(),
                    'email' => $request->user()->getEmail(),
                    'is_admin' => $request->user()->hasRole('admin'),
                    'can_create_party' => $request->user()->can('create', Party::class),
                ] : null,
            ],
            'flash' => fn (): array => collect(['success', 'error', 'info', 'warning'])
                ->mapWithKeys(fn (string $key): array => [$key => $request->session()->get($key)])
                ->all(),
            'impersonating' => fn (): ?array => $request->user() && $request->session()->has('impersonator_id')
                ? ['name' => $request->user()->nickname, 'stopUrl' => route('admin.impersonate.stop', absolute: false)]
                : null,
            'adminLinks' => fn (): array => $request->user()?->hasRole('admin')
                ? [
                    ['label' => 'Pulse', 'href' => url('/pulse')],
                    ['label' => 'Horizon', 'href' => url('/horizon')],
                ]
                : [],
            'parties' => fn (): array => $request->user()
                ? $request->user()->memberParties->map(fn ($party): array => [
                    'code' => $party->code,
                    'name' => $party->name,
                ])->values()->all()
                : [],
        ];
    }
}
