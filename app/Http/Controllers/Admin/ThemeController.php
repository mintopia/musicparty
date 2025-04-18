<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ThemeUpdateRequest;
use App\Models\Theme;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function create()
    {
        $default = Theme::whereCode('default')->first();
        if ($default) {
            $theme = clone $default;
        } else {
            $theme = new Theme();
        }
        $theme->name = null;
        $theme->readonly = false;
        $theme->active = false;
        return view('admin.themes.create', [
            'theme' => $theme,
        ]);
    }

    public function store(ThemeUpdateRequest $request)
    {
        $theme = new Theme();
        $this->updateObject($theme, $request);
        return response()->redirectToRoute('admin.settings.index')->with('successMessage', 'The theme has been created');
    }

    public function edit(Theme $theme)
    {
        return view('admin.themes.edit', [
            'theme' => $theme,
        ]);
    }

    public function update(ThemeUpdateRequest $request, Theme $theme)
    {
        $this->updateObject($theme, $request);
        return response()->redirectToRoute('admin.settings.index')->with('successMessage', 'The theme has been updated');
    }

    public function delete(Theme $theme)
    {
        if ($theme->readonly) {
            abort(400);
        }
        return view('admin.themes.delete', [
            'theme' => $theme,
        ]);
    }

    public function destroy(Theme $theme)
    {
        if ($theme->readonly) {
            abort(400);
        }
        $theme->delete();
        return response()->redirectToRoute('admin.settings.index')->with('successMessage', 'The theme has been deleted');
    }

    protected function updateObject(Theme $theme, Request $request)
    {
        $theme->active = (bool)$request->input('active');
        $theme->dark_mode = (bool)$request->input('dark_mode');
        if (!$theme->readonly) {
            $map = [
                'name',
                'css',
                'primary',
                'nav_background',
            ];
            foreach ($map as $prop) {
                $theme->{$prop} = $request->input($prop);
            }
        }
        $theme->save();
    }
}
