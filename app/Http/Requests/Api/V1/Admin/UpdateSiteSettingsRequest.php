<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingsRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:200'],
            'terms_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https'],
            'privacy_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https'],
            'default_party' => ['sometimes', 'nullable', 'string', 'exists:parties,code'],
            'logo_light' => ['sometimes', 'nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'logo_dark' => ['sometimes', 'nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['sometimes', 'nullable', 'file', 'mimes:png,ico', 'max:512'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter a site name.',
            'name.min' => 'The site name must be at least 2 characters.',
            'name.max' => 'The site name may not be longer than 200 characters.',
            'terms_url.url' => 'The terms of service link must be a valid http or https URL.',
            'privacy_url.url' => 'The privacy policy link must be a valid http or https URL.',
            'default_party.exists' => 'That party does not exist.',
            'logo_light.mimes' => 'The light logo must be a PNG, JPG or WebP image.',
            'logo_light.max' => 'The light logo may not be larger than 2 MB.',
            'logo_dark.mimes' => 'The dark logo must be a PNG, JPG or WebP image.',
            'logo_dark.max' => 'The dark logo may not be larger than 2 MB.',
            'favicon.mimes' => 'The favicon must be a PNG or ICO image.',
            'favicon.max' => 'The favicon may not be larger than 512 KB.',
        ];
    }
}
