<?php

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartyLibrespotRequest extends FormRequest
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
            'event' => 'required|string|in:changed,started,stopped,preloading,paused,playing,volume_set',
            'oldTrackId' => 'required_if:event,changed|string',
            'newTrackId' => 'required_if:event,changed|string',
            'trackId' => [
                Rule::requiredIf(in_array($this->input('event'), ['started', 'stopped', 'preloading', 'paused', 'playing'])),
                'string',
            ],
            'durationMs' => [
                Rule::requiredIf(in_array($this->input('event'), ['paused', 'playing'])),
                'int',
            ],
            'positionMs' => [
                Rule::requiredIf(in_array($this->input('event'), ['paused', 'playing'])),
                'int',
            ],
            'volume' => 'required_if:event,volume_set|int',
        ];
    }
}
