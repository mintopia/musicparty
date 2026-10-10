<?php

namespace App\Http\Requests\Party;

use App\Domain\Party\Models\Party;
use App\Domain\Theming\ThemeTokens;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartyThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $party = $this->route('party');

        return $party instanceof Party && ($this->user()?->can('update', $party) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'light' => ['sometimes', 'array:'.implode(',', ThemeTokens::PARTY_KEYS)],
            'dark' => ['sometimes', 'array:'.implode(',', ThemeTokens::PARTY_KEYS)],
            'font' => ['sometimes', 'nullable', 'string', Rule::in(ThemeTokens::fontKeys())],
            'tv_layout' => ['sometimes', 'string', Rule::in(ThemeTokens::tvLayoutKeys())],
            'logo' => ['sometimes', 'nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'background' => ['sometimes', 'nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_background' => ['sometimes', 'boolean'],
        ];

        foreach (['light', 'dark'] as $scheme) {
            foreach (ThemeTokens::PARTY_KEYS as $key) {
                $rules[$scheme.'.'.$key] = ['sometimes', 'nullable', 'string', 'regex:'.ThemeTokens::HEX_PATTERN];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'light.array' => 'Only the primary, accent, background, surface, text and danger colours can be overridden.',
            'dark.array' => 'Only the primary, accent, background, surface, text and danger colours can be overridden.',
            'light.*.regex' => 'Each light colour must be a hex value like #1a2b3c.',
            'dark.*.regex' => 'Each dark colour must be a hex value like #1a2b3c.',
            'light.*.string' => 'Each light colour must be a hex value like #1a2b3c.',
            'dark.*.string' => 'Each dark colour must be a hex value like #1a2b3c.',
            'font.in' => 'Choose one of the available fonts.',
            'tv_layout.in' => 'Choose one of the available TV layouts.',
            'logo.mimes' => 'The logo must be a PNG, JPG or WebP image.',
            'logo.max' => 'The logo may not be larger than 2 MB.',
            'background.mimes' => 'The background must be a PNG, JPG or WebP image.',
            'background.max' => 'The background may not be larger than 2 MB.',
        ];
    }
}
