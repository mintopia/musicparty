<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
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
            'nickname' => 'required|string|min:2|max:32',
            'terms' => 'accepted',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.required' => 'Please choose a nickname.',
            'nickname.min' => 'Your nickname must be at least :min characters.',
            'nickname.max' => 'Your nickname may not be longer than :max characters.',
            'terms.accepted' => 'You must accept the terms of service to continue.',
        ];
    }
}
