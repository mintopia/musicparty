<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'active' => 'sometimes|bool|nullable',
            'dark_mode' => 'sometimes|bool|nullable',
        ];
        if (!$this->theme || !$this->theme->readonly) {
            $rules = array_merge($rules, [
                'name' => 'required|string|max:100',
                'css' => 'sometimes|string|nullable',
                'primary' => 'required|hex_color',
                'nav_background' => 'required|hex_color',
                'seat_available' => 'required|hex_color',
                'seat_disabled' => 'required|hex_color',
                'seat_taken' => 'required|hex_color',
                'seat_clan' => 'required|hex_color',
                'seat_selected' => 'required|hex_color',
            ]);
        }
        return $rules;
    }
}
