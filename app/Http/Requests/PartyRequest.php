<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartyRequest extends FormRequest
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
        if ($this->party !== null) {
            // We have a party, use the party owner's playlist
            $playlists = $this->party->user->getPlaylists();
        } else {
            // We don't have a party, use our playlists
            $playlists = $this->user()->getPlaylists();
        }
        return [
            'name' => 'required|string|max:50',
            'backup_playlist_id' => [
                'required',
                'string',
                'in:other,' . collect($playlists)->pluck('id')->implode(','),
            ],
            'custom_backup_playlist_id' => 'required_if:backup_playlist_id,other|string|nullable',
            'allow_requests' => 'sometimes|boolean',
            'explicit' => 'sometimes|boolean',
            'downvotes' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
            'queue' => 'sometimes|boolean',
            'force' => 'sometimes|boolean',
            'poll' => 'sometimes|boolean',
            'show_qrcode' => 'sometimes|boolean',
            'downvotes_per_hour' => 'sometimes|integer|min:1|nullable',
            'device_id' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'min_song_length' => 'sometimes|integer|min:1|nullable',
            'max_song_length' => 'sometimes|integer|min:1|nullable',
            'no_repeat_interval' => 'sometimes|integer|min:1|nullable',
            'history_playlist_id' => [
                'sometimes',
                'string',
                'in:other,' . collect($playlists)->pluck('id')->push('none')->implode(','),
                'nullable',
            ],
        ];
    }
}
