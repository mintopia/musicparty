<?php

namespace App\Http\Requests;

use App\Domain\Party\Models\Party;
use App\Domain\Playback\Control;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ControlPlaybackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $party = $this->route('party');

        return $party instanceof Party && $this->user()?->can('control', $party) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match (Control::tryFrom((string) $this->route('control'))) {
            null => [
                'position_ms' => ['integer', 'min:0'],
                'level' => ['integer', 'min:0', 'max:100'],
            ],
            Control::Seek => ['position_ms' => ['required', 'integer', 'min:0']],
            Control::Volume => ['level' => ['required', 'integer', 'min:0', 'max:100']],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'position_ms.required' => 'Choose a position to seek to.',
            'position_ms.integer' => 'The seek position must be a whole number of milliseconds.',
            'position_ms.min' => 'The seek position cannot be negative.',
            'level.required' => 'Choose a volume level.',
            'level.integer' => 'The volume must be a whole number from 0 to 100.',
            'level.min' => 'The volume must be between 0 and 100.',
            'level.max' => 'The volume must be between 0 and 100.',
        ];
    }

    public function control(): Control
    {
        return Control::from((string) $this->route('control'));
    }

    public function value(): ?int
    {
        return match ($this->control()) {
            Control::Seek => $this->integer('position_ms'),
            Control::Volume => $this->integer('level'),
            default => null,
        };
    }
}
