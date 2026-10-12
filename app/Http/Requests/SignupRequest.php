<?php

namespace App\Http\Requests;

use App\Domain\Admin\SiteSettings;
use App\Rules\UniqueNickname;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
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
    public function rules(SiteSettings $settings): array
    {
        return [
            'nickname' => ['required', 'string', 'min:1', 'max:32', new UniqueNickname($this->user()?->id)],
            'terms' => $settings->get('terms_url') === null ? 'nullable' : 'accepted',
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
            'terms.accepted' => 'You must accept the terms of service to continue.',
        ];
    }
}
