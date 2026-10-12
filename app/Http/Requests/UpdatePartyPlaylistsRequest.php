<?php

namespace App\Http\Requests;

use App\Domain\Music\Support\PlaylistReference;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePartyPlaylistsRequest extends FormRequest
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
            'fallback_playlist' => 'present|nullable|string|max:512',
            'history_playlist' => 'present|nullable|string|max:512',
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (['fallback_playlist', 'history_playlist'] as $field) {
                $value = $this->input($field);

                if (is_string($value) && trim($value) !== '' && PlaylistReference::parse($value) === null) {
                    $validator->errors()->add($field, 'Enter a playlist link, URI or id.');
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fallback_playlist.present' => 'The fallback playlist must be provided, or empty to clear it.',
            'history_playlist.present' => 'The history playlist must be provided, or empty to clear it.',
        ];
    }

    public function fallbackPlaylistId(): ?string
    {
        return PlaylistReference::parse($this->input('fallback_playlist'));
    }

    public function historyPlaylistId(): ?string
    {
        return PlaylistReference::parse($this->input('history_playlist'));
    }
}
