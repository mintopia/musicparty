<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\EnsureSocialProvider;
use App\Domain\Admin\Actions\UpdateSocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProviderRequest;
use App\Http\Resources\V1\AdminSocialProviderResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.show');
    }

    public function edit(string $provider, EnsureSocialProvider $ensureProvider): Response
    {
        return Inertia::render('Admin/Providers/Edit', [
            'provider' => new AdminSocialProviderResource($ensureProvider->handle($provider))->resolve(),
        ]);
    }

    public function update(UpdateProviderRequest $request, string $provider, UpdateSocialProvider $updateProvider): RedirectResponse
    {
        $updateProvider->handle(
            $request->user() ?? throw new AuthenticationException,
            $provider,
            $request->has('enabled') ? $request->boolean('enabled') : null,
            $request->validated('settings') ?? [],
            $request->has('auth_enabled') ? $request->boolean('auth_enabled') : null,
        );

        return redirect()->route('admin.settings.show')->with('success', 'Provider saved.');
    }
}
