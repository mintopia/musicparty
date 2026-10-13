<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ThemeUpdateRequest;
use App\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    public function create(): Response
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

        return Inertia::render('Admin/Themes/Create', [
            'theme' => $this->themeProps($theme),
            'colourTokens' => $this->colourTokenProps(),
        ]);
    }

    public function store(ThemeUpdateRequest $request): RedirectResponse
    {
        $theme = new Theme;
        $this->updateObject($theme, $request);

        return response()->redirectToRoute('admin.settings.index')->with('successMessage', 'The theme has been created');
    }

    public function edit(Theme $theme): Response
    {
        return Inertia::render('Admin/Themes/Edit', [
            'theme' => $this->themeProps($theme),
            'colourTokens' => $this->colourTokenProps(),
        ]);
    }

    public function update(ThemeUpdateRequest $request, Theme $theme): RedirectResponse
    {
        $this->updateObject($theme, $request);

        return response()->redirectToRoute('admin.settings.index')->with('successMessage', 'The theme has been updated');
    }

    public function delete(Theme $theme): Response
    {
        if ($theme->readonly) {
            abort(400);
        }

        return Inertia::render('Admin/Themes/Delete', [
            'theme' => ['id' => $theme->id, 'name' => $theme->name],
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

    /**
     * @return array{id: int|null, name: string, css: string, tokens: array<string, string>, active: bool, dark_mode: bool, readonly: bool}
     */
    protected function themeProps(Theme $theme): array
    {
        return [
            'id' => $theme->id,
            'name' => (string) $theme->name,
            'css' => (string) $theme->css,
            'tokens' => array_map('strval', (array) $theme->tokens),
            'active' => (bool) $theme->active,
            'dark_mode' => (bool) $theme->dark_mode,
            'readonly' => (bool) $theme->readonly,
        ];
    }

    /**
     * @return list<array{token: string, label: string, required: bool}>
     */
    protected function colourTokenProps(): array
    {
        $tokens = [];
        foreach (Theme::COLOUR_TOKENS as $token => $label) {
            $tokens[] = [
                'token' => $token,
                'label' => $label,
                'required' => in_array($token, ['primary', 'nav-background'], true),
            ];
        }

        return $tokens;
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
