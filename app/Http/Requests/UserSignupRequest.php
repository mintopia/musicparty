<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserSignupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $terms = Setting::fetch('terms');
        $privacy = Setting::fetch('privacypolicy');
        if ($terms || $privacy) {
            $combined = [];
            if ($terms) {
                $combined[] = 'Terms and Conditions';
            }
            if ($privacy) {
                $combined[] = 'Privacy Policy';
            }
            return [
                'terms.accepted' => 'You must agree to the ' . implode(' and ', $combined),
            ];
        }
        return [];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'nickname' => [
                'required',
                'string',
                'max:255',
                'min:2',
                Rule::unique('users', 'nickname')->ignore($this->user()->id),
            ],
        ];

        if (Setting::fetch('terms') || Setting::fetch('privacypolicy')) {
            $rules['terms'] = 'accepted';
        }

        return $rules;
    }
}
