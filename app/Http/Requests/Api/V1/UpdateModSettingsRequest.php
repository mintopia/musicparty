<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.required' => 'Provide the settings to save.',
            'settings.array' => 'The settings must be an object of values.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $settings = $this->input('settings');

        return is_array($settings) ? $settings : [];
    }
}
