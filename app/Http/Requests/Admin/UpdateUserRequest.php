<?php

namespace App\Http\Requests\Admin;

use App\Domain\Admin\AdminRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'nickname' => ['required', 'string', 'max:255'],
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::enum(AdminRole::class)],
            'agreed_terms' => ['required', 'boolean'],
            'completed_signup' => ['required', 'boolean'],
            'suspended' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.required' => 'Enter a nickname.',
            'nickname.max' => 'The nickname may not be longer than 255 characters.',
            'roles.present' => 'Roles must be submitted.',
            'roles.array' => 'Roles must be a list.',
            'roles.*.enum' => 'Roles must be admin or create-party.',
            'agreed_terms.required' => 'Choose whether the user agreed to the terms.',
            'agreed_terms.boolean' => 'Agreed terms must be true or false.',
            'completed_signup.required' => 'Choose whether the user completed signup.',
            'completed_signup.boolean' => 'Completed signup must be true or false.',
            'suspended.required' => 'Choose whether the user is suspended.',
            'suspended.boolean' => 'Suspended must be true or false.',
        ];
    }
}
