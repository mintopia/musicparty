<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Theming\ColourScheme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ColourSchemeRequest extends FormRequest
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
            'colour_scheme' => ['required', 'string', Rule::enum(ColourScheme::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'colour_scheme.required' => 'Choose a colour scheme.',
            'colour_scheme.enum' => 'The colour scheme must be light, dark or system.',
        ];
    }
}
