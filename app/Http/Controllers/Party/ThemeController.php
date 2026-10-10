<?php

namespace App\Http\Controllers\Party;

use App\Domain\Party\Models\Party;
use App\Domain\Theming\Actions\GetPartyTheme;
use App\Domain\Theming\Actions\ResetPartyTheme;
use App\Domain\Theming\Actions\UpdatePartyTheme;
use App\Domain\Theming\ContrastWarnings;
use App\Domain\Theming\ThemeTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Party\UpdatePartyThemeRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    public function show(Party $party, GetPartyTheme $getPartyTheme, ContrastWarnings $contrastWarnings): Response
    {
        $this->authorize('update', $party);
        $theme = $getPartyTheme->handle($party);

        return Inertia::render('Party/Theme', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'theme' => $theme,
            'fonts' => ThemeTokens::fontList(),
            'tokens' => ThemeTokens::partyTokenList(),
            'layouts' => ThemeTokens::tvLayoutList(),
            'warnings' => $contrastWarnings->for($theme),
        ]);
    }

    public function update(UpdatePartyThemeRequest $request, Party $party, UpdatePartyTheme $updatePartyTheme): RedirectResponse
    {
        $updatePartyTheme->handle($request->user() ?? throw new AuthenticationException, $party, $request->validated());

        return back();
    }

    public function destroy(Request $request, Party $party, ResetPartyTheme $resetPartyTheme): RedirectResponse
    {
        $resetPartyTheme->handle($request->user() ?? throw new AuthenticationException, $party);

        return back();
    }
}
