<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Actions\SetMusicProviderEnabled;
use App\Domain\Admin\PairingToggles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdatePairingToggleRequest;
use App\Http\Resources\V1\AdminMusicProviderResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MusicProviderController extends Controller
{
    public function index(PairingToggles $toggles): AnonymousResourceCollection
    {
        return AdminMusicProviderResource::collection(
            collect(array_keys((array) config('musicparty.music_providers', [])))
                ->map(fn (string $id): array => $this->row($toggles, $id))
                ->values(),
        );
    }

    public function update(UpdatePairingToggleRequest $request, string $musicProvider, SetMusicProviderEnabled $setEnabled, PairingToggles $toggles): AdminMusicProviderResource
    {
        abort_unless(array_key_exists($musicProvider, (array) config('musicparty.music_providers', [])), 404);

        $setEnabled->handle(
            $request->user() ?? throw new AuthenticationException,
            $musicProvider,
            $request->boolean('enabled'),
        );

        return new AdminMusicProviderResource($this->row($toggles, $musicProvider));
    }

    /**
     * @return array{id: string, label: string, enabled: bool}
     */
    private function row(PairingToggles $toggles, string $id): array
    {
        return [
            'id' => $id,
            'label' => (string) config("musicparty.music_providers.{$id}.label", $id),
            'enabled' => $toggles->providerEnabled($id),
        ];
    }
}
