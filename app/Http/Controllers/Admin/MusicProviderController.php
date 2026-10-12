<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\SetMusicProviderEnabled;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMusicProviderRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;

class MusicProviderController extends Controller
{
    public function update(UpdateMusicProviderRequest $request, string $provider, SetMusicProviderEnabled $setEnabled): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $setEnabled->handle($request->user() ?? throw new AuthenticationException, $provider, $enabled);

        $label = (string) config("musicparty.music_providers.{$provider}.label", $provider);

        return back()->with('success', $label.($enabled ? ' enabled.' : ' disabled.'));
    }
}
