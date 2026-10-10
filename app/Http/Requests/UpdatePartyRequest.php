<?php

namespace App\Http\Requests;

use App\Domain\Queue\SelectionMode;
use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'allow_requests' => 'sometimes|boolean',
            'max_requests' => 'sometimes|nullable|integer|min:1|max:1000',
            'explicit' => 'sometimes|boolean',
            'min_song_length' => 'sometimes|nullable|integer|min:0|max:86400',
            'max_song_length' => 'sometimes|nullable|integer|min:1|max:86400',
            'no_repeat_interval' => 'sometimes|nullable|integer|min:0',
            'hold_requests' => 'sometimes|boolean',
            'downvotes' => 'sometimes|boolean',
            'downvotes_per_hour' => 'sometimes|nullable|integer|min:0|max:1000',
            'selection_mode' => ['sometimes', Rule::enum(SelectionMode::class)],
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
            'allow_requests.boolean' => 'Accepting requests must be either on or off.',
            'max_requests.integer' => 'The request limit must be a whole number.',
            'max_requests.min' => 'The request limit must be at least :min.',
            'max_requests.max' => 'The request limit may not be more than :max.',
            'explicit.boolean' => 'The explicit setting must be true or false.',
            'hold_requests.boolean' => 'Holding requests for approval must be either on or off.',
            'downvotes.boolean' => 'Downvotes must be either on or off.',
            'downvotes_per_hour.integer' => 'The downvote limit must be a whole number.',
            'downvotes_per_hour.min' => 'The downvote limit cannot be negative.',
            'downvotes_per_hour.max' => 'The downvote limit may not be more than :max per hour.',
            'selection_mode.enum' => 'The selection mode must be deterministic or weighted.',
        ];
    }
}
