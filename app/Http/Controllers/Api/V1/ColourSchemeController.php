<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Theming\Actions\SetColourScheme;
use App\Domain\Theming\ColourScheme;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ColourSchemeRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class ColourSchemeController extends Controller
{
    public function update(ColourSchemeRequest $request, SetColourScheme $setColourScheme): JsonResponse
    {
        $user = $setColourScheme->handle($request->user() ?? throw new AuthenticationException, ColourScheme::from($request->string('colour_scheme')->toString()));

        return response()->json(['data' => ['colour_scheme' => $user->colour_scheme->value]]);
    }
}
