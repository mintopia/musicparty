<?php

namespace App\Http\Requests\Profile;

use App\Rules\UniqueNickname;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'nickname' => ['required', 'string', 'min:1', 'max:32', new UniqueNickname($this->user()?->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.required' => 'Please choose a nickname.',
            'nickname.min' => 'Please choose a nickname.',
            'nickname.max' => 'Your nickname may not be longer than :max characters.',
        ];
    }
}
