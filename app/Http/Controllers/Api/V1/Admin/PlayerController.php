<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Actions\SetPlayerEnabled;
use App\Domain\Admin\PairingToggles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdatePairingToggleRequest;
use App\Http\Resources\V1\AdminPlayerResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlayerController extends Controller
{
    public function index(PairingToggles $toggles): AnonymousResourceCollection
    {
        return AdminPlayerResource::collection(
            collect(array_keys((array) config('musicparty.players', [])))
                ->map(fn (string $id): array => $this->row($toggles, $id))
                ->values(),
        );
    }

    public function update(UpdatePairingToggleRequest $request, string $player, SetPlayerEnabled $setEnabled, PairingToggles $toggles): AdminPlayerResource
    {
        abort_unless(array_key_exists($player, (array) config('musicparty.players', [])), 404);

        $setEnabled->handle(
            $request->user() ?? throw new AuthenticationException,
            $player,
            $request->boolean('enabled'),
        );

        return new AdminPlayerResource($this->row($toggles, $player));
    }

    /**
     * @return array{id: string, label: string, enabled: bool}
     */
    private function row(PairingToggles $toggles, string $id): array
    {
        return [
            'id' => $id,
            'label' => (string) config("musicparty.players.{$id}.label", $id),
            'enabled' => $toggles->playerEnabled($id),
        ];
    }
}
