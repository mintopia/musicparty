<?php

namespace App\Http\Controllers;

use App\Domain\Theming\Actions\SetColourScheme;
use App\Domain\Theming\ColourScheme;
use App\Http\Requests\Api\V1\ColourSchemeRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;

class ColourSchemeController extends Controller
{
    public function update(ColourSchemeRequest $request, SetColourScheme $setColourScheme): RedirectResponse
    {
        $setColourScheme->handle($request->user() ?? throw new AuthenticationException, ColourScheme::from($request->string('colour_scheme')->toString()));

        return back();
    }
}
