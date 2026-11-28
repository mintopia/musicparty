<?php

namespace App\Http\Requests;

use App\Enums\Purpose;
use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Http\Concerns\ResolvesAuthenticatedUser;
use App\Models\Party;
use App\Services\Music\MusicProviderFactory;
use App\Services\PartyCodeGenerator;
use App\Services\PartyProviderOptions;
use App\Services\Playback\ContextPlaylist;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
        if (! $this->route('party') instanceof Party && ! $this->has('provider_id')) {
            $this->merge(['provider_id' => MusicProviderFactory::DEFAULT_PROVIDER_ID]);
        }
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $party = $this->route('party');
            $newProviderId = $this->input('provider_id');
            if (! $party instanceof Party && is_string($newProviderId) && ! $validator->errors()->has('provider_id')) {
                $factory = app(MusicProviderFactory::class);
                if (! $factory->forUser($newProviderId, $this->resolveAuthenticatedUser($this), Purpose::Control)->isLinked()) {
                    $validator->errors()->add('provider_id', 'Link your account for this music provider before creating a party.');
                }

                return;
            }
            $playlistId = $this->input('context_playlist_id');
            if (! $party instanceof Party || ! is_string($playlistId) || $playlistId === '' || $playlistId === $party->context_playlist_id || $validator->errors()->has('context_playlist_id')) {
                return;
            }

            try {
                $owned = app(MusicProviderFactory::class)->forParty($party, Purpose::Control)->ownedPlaylist($playlistId);
            } catch (ProviderRelinkRequiredException|ProviderBackoffException) {
                $validator->errors()->add('context_playlist_id', 'The playlist could not be checked right now. Try again shortly.');

                return;
            }
            if ($owned === null || $owned->name !== ContextPlaylist::NAME) {
                $validator->errors()->add('context_playlist_id', 'Use the playlist named "'.ContextPlaylist::NAME."\" owned by {$party->user->nickname}'s Spotify account");
            }
        }];
    }

    public static function normalisePlaylistId(string $input): string
    {
        $input = trim($input);
        if (preg_match('#^spotify:playlist:([^:?/\s]+)$#i', $input, $matches) === 1) {
            return $matches[1];
        }
        if (preg_match('#^https?://open\.spotify\.com/(?:intl-[a-z-]+/)?playlist/([^/?\#\s]+)#i', $input, $matches) === 1) {
            return $matches[1];
        }

        return $input;
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

        $codeRules = [];
        if ($party instanceof Party && $this->user()?->can('admin')) {
            $codeRules = ['code' => ['sometimes', 'string', 'regex:'.PartyCodeGenerator::FORMAT, Rule::unique('parties', 'code')->ignore($party->getKey())]];
        }

        return $codeRules + [
            'provider_id' => $party instanceof Party
                ? 'prohibited'
                : ['required', 'string', Rule::in(app(MusicProviderFactory::class)->selectableIds())],
            'name' => 'required|string|max:50',
            'country' => 'sometimes|string|alpha|size:2',
            'timezone' => 'sometimes|nullable|string|timezone:all',
            'backup_playlist_id' => [
                $party instanceof Party ? 'required' : 'nullable',
                'string',
                'in:other,'.collect($playlists)->pluck('id')->implode(','),
            ],
            'custom_backup_playlist_id' => ['required_if:backup_playlist_id,other', 'nullable', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->input('backup_playlist_id') === 'other' && ! preg_match('/^[A-Za-z0-9]+$/', self::normalisePlaylistId((string) $value))) {
                    $fail('Enter a Spotify playlist link or ID.');
                }
            }],
            'allow_requests' => 'sometimes|boolean',
            'explicit' => 'sometimes|boolean',
            'downvotes' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
            'queue' => 'sometimes|boolean',
            'force' => 'sometimes|boolean',
            'poll' => 'sometimes|boolean',
            'show_qrcode' => 'sometimes|boolean',
            'flag_requests' => 'sometimes|boolean',
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
            'context_playlist_id' => 'sometimes|nullable|string|max:64',
            'history_playlist_id' => [
                'sometimes',
                'string',
                'in:other,'.collect($playlists)->pluck('id')->push('none')->implode(','),
                'nullable',
            ],
        ];
    }
}
