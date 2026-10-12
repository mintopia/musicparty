<?php

namespace App\Http\Requests;

use App\Enums\PartyModerationType;
use App\Models\PartyModeration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PartyModerationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reject regex rules that do not compile or exceed the length cap.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('regex') && is_string($this->input('value')) && ! PartyModeration::isValidRegex($this->input('value'))) {
                $validator->errors()->add('value', 'The value must be a valid regular expression of at most '.PartyModeration::MAX_REGEX_LENGTH.' characters.');
            }
        });
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                Rule::in(collect(PartyModerationType::cases())->pluck('name')->toArray()),
            ],
            'enabled' => 'boolean',
            'regex' => 'boolean',
            'value' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
