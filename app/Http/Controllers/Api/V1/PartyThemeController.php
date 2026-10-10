<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Party\Models\Party;
use App\Domain\Theming\Actions\GetPartyTheme;
use App\Domain\Theming\Actions\ResetPartyTheme;
use App\Domain\Theming\Actions\UpdatePartyTheme;
use App\Domain\Theming\ContrastWarnings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Party\UpdatePartyThemeRequest;
use App\Http\Resources\V1\PartyThemeResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class PartyThemeController extends Controller
{
    public function show(Party $party, GetPartyTheme $getPartyTheme, ContrastWarnings $contrastWarnings): PartyThemeResource
    {
        $theme = $getPartyTheme->handle($party);

        return new PartyThemeResource($theme, $contrastWarnings->for($theme));
    }

    public function update(UpdatePartyThemeRequest $request, Party $party, UpdatePartyTheme $updatePartyTheme, ContrastWarnings $contrastWarnings): PartyThemeResource
    {
        $theme = $updatePartyTheme->handle($request->user() ?? throw new AuthenticationException, $party, $request->validated());

        return new PartyThemeResource($theme, $contrastWarnings->for($theme));
    }

    public function destroy(Request $request, Party $party, ResetPartyTheme $resetPartyTheme, ContrastWarnings $contrastWarnings): PartyThemeResource
    {
        $theme = $resetPartyTheme->handle($request->user() ?? throw new AuthenticationException, $party);

        return new PartyThemeResource($theme, $contrastWarnings->for($theme));
    }
}
