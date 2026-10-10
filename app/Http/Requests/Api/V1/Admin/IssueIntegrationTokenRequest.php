<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Domain\Admin\IntegrationAbility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueIntegrationTokenRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', Rule::enum(IntegrationAbility::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the token a name.',
            'name.max' => 'The name may not be longer than 100 characters.',
            'abilities.required' => 'Choose at least one ability.',
            'abilities.min' => 'Choose at least one ability.',
            'abilities.*.enum' => 'Abilities must be read or export.',
        ];
    }
}
