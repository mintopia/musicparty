<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\ListSocialProviders;
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
    public function index(ListSocialProviders $listProviders): Response
    {
        return Inertia::render('Admin/Providers', [
            'providers' => AdminSocialProviderResource::collection($listProviders->handle())->resolve(),
        ]);
    }

    public function update(UpdateProviderRequest $request, string $provider, UpdateSocialProvider $updateProvider): RedirectResponse
    {
        $updateProvider->handle(
            $request->user() ?? throw new AuthenticationException,
            $provider,
            $request->has('enabled') ? $request->boolean('enabled') : null,
            $request->validated('settings') ?? [],
        );

        return back();
    }
}
