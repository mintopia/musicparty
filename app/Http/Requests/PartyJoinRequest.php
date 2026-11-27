<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartyJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        $this->merge(['code' => is_string($code) ? strtoupper(trim($code)) : $code]);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Z]{4}$/', Rule::exists('parties', 'code')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Enter the four-letter party code.',
            'code.regex' => 'Party codes are four letters, no digits.',
            'code.exists' => "We can't find a party with that code. Check the TV or ask the host.",
        ];
    }
}
