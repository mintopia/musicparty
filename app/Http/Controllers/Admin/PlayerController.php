<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\SetPlayerEnabled;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePlayerRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;

class PlayerController extends Controller
{
    public function update(UpdatePlayerRequest $request, string $player, SetPlayerEnabled $setEnabled): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $setEnabled->handle($request->user() ?? throw new AuthenticationException, $player, $enabled);

        $label = (string) config("musicparty.players.{$player}.label", $player);

        return back()->with('success', $label.($enabled ? ' enabled.' : ' disabled.'));
    }
}
