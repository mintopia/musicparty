<?php

namespace App\Http\Requests\Party;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IssuePlayerTokenRequest extends FormRequest
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
            'name' => 'required|string|max:64',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A label for the Player Token is required.',
            'name.string' => 'The Player Token label must be a string.',
            'name.max' => 'The Player Token label may not be longer than 64 characters.',
        ];
    }
}
