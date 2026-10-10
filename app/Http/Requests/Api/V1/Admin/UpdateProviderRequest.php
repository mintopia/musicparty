<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderRequest extends FormRequest
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
        return [
            'enabled' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
            'settings.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enabled.boolean' => 'Enabled must be true or false.',
            'settings.array' => 'The credentials must be a list of fields.',
            'settings.*.string' => 'Each credential must be text.',
            'settings.*.max' => 'A credential may not be longer than 500 characters.',
        ];
    }
}
