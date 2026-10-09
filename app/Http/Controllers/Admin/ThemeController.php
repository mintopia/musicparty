<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Theming\Actions\GetInstanceTheme;
use App\Domain\Theming\Actions\ResetInstanceTheme;
use App\Domain\Theming\Actions\UpdateInstanceTheme;
use App\Domain\Theming\ContrastWarnings;
use App\Domain\Theming\ThemeTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateThemeRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    public function show(GetInstanceTheme $getInstanceTheme, ContrastWarnings $contrastWarnings): Response
    {
        $theme = $getInstanceTheme->handle();

        return Inertia::render('Admin/Theme', [
            'theme' => $theme,
            'defaults' => ThemeTokens::defaults(),
            'fonts' => ThemeTokens::fontList(),
            'tokens' => ThemeTokens::tokenList(),
            'warnings' => $contrastWarnings->for($theme),
        ]);
    }

    public function update(UpdateThemeRequest $request, UpdateInstanceTheme $updateInstanceTheme): RedirectResponse
    {
        $updateInstanceTheme->handle($request->user() ?? throw new AuthenticationException, $request->validated());

        return back();
    }

    public function destroy(Request $request, ResetInstanceTheme $resetInstanceTheme): RedirectResponse
    {
        $resetInstanceTheme->handle($request->user() ?? throw new AuthenticationException);

        return back();
    }
}
