<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Actions\ListSocialProviders;
use App\Domain\Admin\Actions\UpdateSocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateProviderRequest;
use App\Http\Resources\V1\AdminSocialProviderResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProviderController extends Controller
{
    public function index(ListSocialProviders $listProviders): AnonymousResourceCollection
    {
        return AdminSocialProviderResource::collection($listProviders->handle());
    }

    public function update(UpdateProviderRequest $request, string $provider, UpdateSocialProvider $updateProvider): AdminSocialProviderResource
    {
        return new AdminSocialProviderResource($updateProvider->handle(
            $request->user() ?? throw new AuthenticationException,
            $provider,
            $request->has('enabled') ? $request->boolean('enabled') : null,
            $request->validated('settings') ?? [],
        ));
    }
}
