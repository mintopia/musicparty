<?php

namespace App\Http\Requests\Admin;

use App\Domain\Theming\ThemeTokens;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'light' => ['sometimes', 'array:'.implode(',', ThemeTokens::keys())],
            'dark' => ['sometimes', 'array:'.implode(',', ThemeTokens::keys())],
            'font' => ['sometimes', 'string', Rule::in(ThemeTokens::fontKeys())],
        ];

        foreach (['light', 'dark'] as $scheme) {
            foreach (ThemeTokens::keys() as $key) {
                $rules[$scheme.'.'.$key] = ['sometimes', 'string', 'regex:'.ThemeTokens::HEX_PATTERN];
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
            'light.array' => 'The light theme contains an unknown colour token.',
            'dark.array' => 'The dark theme contains an unknown colour token.',
            'light.*.regex' => 'Each light colour must be a hex value like #1a2b3c.',
            'dark.*.regex' => 'Each dark colour must be a hex value like #1a2b3c.',
            'light.*.string' => 'Each light colour must be a hex value like #1a2b3c.',
            'dark.*.string' => 'Each dark colour must be a hex value like #1a2b3c.',
            'font.in' => 'Choose one of the available fonts.',
        ];
    }
}
