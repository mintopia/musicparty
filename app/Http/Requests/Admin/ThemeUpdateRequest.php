<?php

namespace App\Http\Requests\Admin;

use App\Models\Theme;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ThemeUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'active' => 'sometimes|bool|nullable',
            'dark_mode' => 'sometimes|bool|nullable',
        ];
        if (! $this->theme || ! $this->theme->readonly) {
            $rules = array_merge($rules, [
                'name' => [
                    'required', 'string', 'max:100',
                    function (string $attribute, mixed $value, Closure $fail): void {
                        $code = Str::slug((string) $value);
                        if ($code === '') {
                            $fail('The name must contain letters or numbers.');
                        } elseif (Theme::whereCode($code)->where('id', '<>', $this->theme?->id)->exists()) {
                            $fail('A theme with this name already exists.');
                        }
                    },
                ],
                'css' => 'sometimes|string|nullable',
                'tokens' => 'required|array',
                'tokens.primary' => 'required|hex_color',
                'tokens.nav-background' => 'required|hex_color',
                'tokens.*' => 'nullable|hex_color',
            ]);
        }

        return $rules;
    }
}
