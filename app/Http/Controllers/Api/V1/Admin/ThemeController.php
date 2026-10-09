<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Theming\Actions\GetInstanceTheme;
use App\Domain\Theming\Actions\ResetInstanceTheme;
use App\Domain\Theming\Actions\UpdateInstanceTheme;
use App\Domain\Theming\ContrastWarnings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateThemeRequest;
use App\Http\Resources\V1\InstanceThemeResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function show(GetInstanceTheme $getInstanceTheme, ContrastWarnings $contrastWarnings): InstanceThemeResource
    {
        $theme = $getInstanceTheme->handle();

        return new InstanceThemeResource($theme, $contrastWarnings->for($theme));
    }

    public function update(UpdateThemeRequest $request, UpdateInstanceTheme $updateInstanceTheme, ContrastWarnings $contrastWarnings): InstanceThemeResource
    {
        $theme = $updateInstanceTheme->handle($request->user() ?? throw new AuthenticationException, $request->validated());

        return new InstanceThemeResource($theme, $contrastWarnings->for($theme));
    }

    public function destroy(Request $request, ResetInstanceTheme $resetInstanceTheme, ContrastWarnings $contrastWarnings): InstanceThemeResource
    {
        $theme = $resetInstanceTheme->handle($request->user() ?? throw new AuthenticationException);

        return new InstanceThemeResource($theme, $contrastWarnings->for($theme));
    }
}
