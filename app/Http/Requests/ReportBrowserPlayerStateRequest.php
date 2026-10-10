<?php

namespace App\Http\Requests;

use App\Domain\Playback\PlaybackStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ReportBrowserPlayerStateRequest extends BrowserPlayerTabRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['required', 'string', Rule::enum(PlaybackStatus::class)],
            'track_id' => ['nullable', 'string', 'max:128'],
            'position_ms' => ['required', 'integer', 'min:0'],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'status.required' => 'The playback status is required.',
            'position_ms.required' => 'The playback position is required.',
        ];
    }
}
