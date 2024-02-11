<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SocialProviderUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'enabled' => 'sometimes|nullable|boolean',
            'auth_enabled' => 'sometimes|nullable|boolean',
        ];
        if ($this->provider->can_be_renamed) {
            $rules['name'] = 'sometimes|string|max:100';
        }
        foreach ($this->provider->settings as $setting) {
            if ($setting->validation) {
                $rules[$setting->code] = $setting->validation;
            }
        }
        return $rules;
    }
}
