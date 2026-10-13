<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ThemeUpdateRequest;
use App\Models\Theme;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function create(): View
    {
        $default = Theme::whereCode('default')->first();
        if ($default) {
            $theme = clone $default;
        } else {
            $theme = new Theme;
        }
        $theme->name = '';
        $theme->readonly = false;
        $theme->active = false;

        return view('admin.themes.create', [
            'theme' => $theme,
        ]);
    }

    public function store(ThemeUpdateRequest $request): RedirectResponse
    {
        $theme = new Theme;
        $this->updateObject($theme, $request);

        return response()->redirectToRoute('admin.settings.index')->with('successMessage', 'The theme has been created');
    }

    public function edit(Theme $theme): View
    {
        return view('admin.themes.edit', [
            'theme' => $theme,
        ]);
    }

    public function update(ThemeUpdateRequest $request, Theme $theme): RedirectResponse
    {
        $this->updateObject($theme, $request);

        return response()->redirectToRoute('admin.settings.index')->with('successMessage', 'The theme has been updated');
    }

    public function delete(Theme $theme): View
    {
        if ($theme->readonly) {
            abort(400);
        }

        return view('admin.themes.delete', [
            'theme' => $theme,
        ]);
    }

    public function destroy(Theme $theme): RedirectResponse
    {
        if ($theme->readonly) {
            abort(400);
        }
        $theme->delete();

        return response()->redirectToRoute('admin.settings.index')->with('successMessage', 'The theme has been deleted');
    }

    protected function updateObject(Theme $theme, Request $request): void
    {
        $theme->active = (bool) $request->input('active');
        $theme->dark_mode = (bool) $request->input('dark_mode');
        if (! $theme->readonly) {
            $map = [
                'name',
                'css',
            ];
            foreach ($map as $prop) {
                $theme->{$prop} = $request->input($prop);
            }
            $theme->tokens = array_filter(
                array_intersect_key((array) $request->input('tokens', []), Theme::COLOUR_TOKENS),
                fn (mixed $value): bool => is_string($value) && $value !== '',
            );
        }
        $theme->save();
    }
}
