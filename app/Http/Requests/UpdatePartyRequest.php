<?php

namespace App\Http\Requests;

use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePartyRequest extends FormRequest
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
            'name' => 'sometimes|string|min:2|max:64',
            'fallback_playlist_id' => 'sometimes|nullable|string|max:255',
            'explicit' => 'sometimes|boolean',
            'min_song_length' => 'sometimes|nullable|integer|min:0|max:86400',
            'max_song_length' => 'sometimes|nullable|integer|min:1|max:86400',
            'no_repeat_interval' => 'sometimes|nullable|integer|min:0',
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $party = $this->route('party');
            $party = $party instanceof Party ? $party : null;
            $min = $this->has('min_song_length') ? $this->input('min_song_length') : $party?->min_song_length;
            $max = $this->has('max_song_length') ? $this->input('max_song_length') : $party?->max_song_length;

            if ($min !== null && $max !== null && (int) $min > (int) $max) {
                $validator->errors()->add('min_song_length', 'The minimum song length may not be longer than the maximum song length.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.min' => 'The party name must be at least :min characters.',
            'name.max' => 'The party name may not be longer than :max characters.',
            'min_song_length.lte' => 'The minimum song length may not be longer than the maximum song length.',
            'min_song_length.integer' => 'The minimum song length must be a whole number of seconds.',
            'max_song_length.integer' => 'The maximum song length must be a whole number of seconds.',
            'no_repeat_interval.integer' => 'The no-repeat interval must be a whole number of seconds.',
            'explicit.boolean' => 'The explicit setting must be true or false.',
        ];
    }
}
