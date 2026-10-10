<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangePartyPlayerRequest extends FormRequest
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
            'player_kind' => 'required|string|max:255',
            'music_provider' => 'nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'player_kind.required' => 'A player kind must be provided.',
            'player_kind.string' => 'The player kind must be a string.',
            'music_provider.string' => 'The Music Provider must be a string, or null to keep the current one.',
        ];
    }
}
