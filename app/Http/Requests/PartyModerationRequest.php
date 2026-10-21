<?php

namespace App\Http\Requests;

use App\Enums\PartyModerationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
