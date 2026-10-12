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
            'device_id' => 'sometimes|nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
        ];
    }

    public function deviceProvided(): bool
    {
        return array_key_exists('device_id', $this->validated());
    }

    public function deviceId(): ?string
    {
        $id = $this->validated('device_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function deviceName(): ?string
    {
        $name = $this->validated('device_name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'player_kind.required' => 'A player kind must be provided.',
            'player_kind.string' => 'The player kind must be a string.',
            'player_kind.max' => 'The player kind may not be longer than 255 characters.',
            'music_provider.max' => 'The Music Provider may not be longer than 255 characters.',
            'device_id.string' => 'The playback device id must be a string, or null for the active device.',
            'device_id.max' => 'The playback device id may not be longer than 255 characters.',
            'device_name.string' => 'The playback device name must be a string.',
            'device_name.max' => 'The playback device name may not be longer than 255 characters.',
            'music_provider.string' => 'The Music Provider must be a string, or null to keep the current one.',
        ];
    }
}
