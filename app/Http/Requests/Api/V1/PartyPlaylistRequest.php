<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PartyPlaylistRequest extends FormRequest
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
            'fallback_playlist_id' => 'present|nullable|string|max:255',
            'history_playlist_id' => 'present|nullable|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fallback_playlist_id.present' => 'The fallback playlist must be provided, or null to clear it.',
            'history_playlist_id.present' => 'The history playlist must be provided, or null to clear it.',
            'fallback_playlist_id.string' => 'The fallback playlist must be a playlist identifier.',
            'history_playlist_id.string' => 'The history playlist must be a playlist identifier.',
        ];
    }
}
