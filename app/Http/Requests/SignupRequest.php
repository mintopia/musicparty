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
            'terms' => $settings->get('terms_url') === null && $settings->get('privacy_url') === null ? 'nullable' : 'accepted',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function consentMessages(): array
    {
        $settings = app(SiteSettings::class);
        $hasTerms = $settings->get('terms_url') !== null;
        $hasPrivacy = $settings->get('privacy_url') !== null;

        $documents = match (true) {
            $hasTerms && $hasPrivacy => 'the Terms and Conditions and Privacy Policy',
            $hasPrivacy => 'the Privacy Policy',
            default => 'the Terms and Conditions',
        };

        return ['terms.accepted' => "You must agree to {$documents}"];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->consentMessages(),
            'nickname.required' => 'Please choose a nickname.',
            'nickname.min' => 'Please choose a nickname.',
            'nickname.max' => 'Your nickname may not be longer than :max characters.',
        ];
    }
}
