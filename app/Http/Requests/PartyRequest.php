<?php

namespace App\Http\Requests;

use App\Http\Concerns\ResolvesAuthenticatedUser;
use App\Models\Party;
use App\Services\Music\MusicProviderFactory;
use App\Services\PartyProviderOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartyRequest extends FormRequest
{
    use ResolvesAuthenticatedUser;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->route('party') instanceof Party && ! $this->has('provider_id')) {
            $this->merge(['provider_id' => MusicProviderFactory::DEFAULT_PROVIDER_ID]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $party = $this->route('party');
        $options = app(PartyProviderOptions::class);
        if ($party instanceof Party) {
            $playlists = $options->playlists($party);
        } else {
            $providerId = $this->input('provider_id');
            $playlists = $options->playlists($options->draft(
                $this->resolveAuthenticatedUser($this),
                is_string($providerId) ? $providerId : null,
            ));
        }

        return [
            'provider_id' => $party instanceof Party
                ? 'prohibited'
                : ['required', 'string', Rule::in(app(MusicProviderFactory::class)->selectableIds())],
            'name' => 'required|string|max:50',
            'country' => 'sometimes|string|alpha|size:2',
            'backup_playlist_id' => [
                'required',
                'string',
                'in:other,'.collect($playlists)->pluck('id')->implode(','),
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
            'max_requests' => 'sometimes|integer|min:1|nullable',
            'history_playlist_id' => [
                'sometimes',
                'string',
                'in:other,'.collect($playlists)->pluck('id')->push('none')->implode(','),
                'nullable',
            ],
        ];
    }
}
