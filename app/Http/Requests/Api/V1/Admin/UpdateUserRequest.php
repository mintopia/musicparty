<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Domain\Identity\Models\User;
use App\Rules\UniqueNickname;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nickname'))) {
            $this->merge(['nickname' => trim($this->input('nickname'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nickname' => ['sometimes', 'required', 'string', 'min:1', 'max:32', new UniqueNickname($this->route('user') instanceof User ? $this->route('user')->id : null)],
            'agreed_terms' => ['sometimes', 'required', 'boolean'],
            'completed_signup' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.required' => 'Enter a nickname.',
            'nickname.max' => 'The nickname may not be longer than :max characters.',
            'agreed_terms.boolean' => 'Agreed terms must be true or false.',
            'completed_signup.boolean' => 'Completed signup must be true or false.',
        ];
    }
}
