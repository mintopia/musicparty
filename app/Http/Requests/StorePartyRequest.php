<?php

namespace App\Http\Requests;

use App\Domain\Music\Actions\SpotifyControlLinkStatus;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Queue\SelectionMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Party::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:64',
            'music_provider' => ['required', 'string', Rule::in(array_keys(config('musicparty.music_providers', [])))],
            'player_kind' => ['required', 'string', Rule::in(array_keys(config('musicparty.players', [])))],
            'downvotes' => 'sometimes|boolean',
            'downvotes_per_hour' => 'sometimes|nullable|integer|min:0|max:1000',
            'selection_mode' => ['sometimes', Rule::enum(SelectionMode::class)],
            'allow_requests' => 'sometimes|boolean',
            'max_requests' => 'sometimes|nullable|integer|min:1|max:1000',
            'hold_requests' => 'sometimes|boolean',
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $catalogue = app(PairingCatalogue::class);
            $kind = $this->string('player_kind')->toString();
            $provider = $this->string('music_provider')->toString();

            if (! $catalogue->isProviderAvailable($provider)) {
                $validator->errors()->add('music_provider', 'That music provider is not available.');

                return;
            }

            if (! $catalogue->isPlayerAvailable($kind, $provider)) {
                $validator->errors()->add('player_kind', 'This player is not available for the chosen music provider.');

                return;
            }

            $user = $this->user();
            $needsLink = $catalogue->player($kind)->requiresHostAccount();

            if ($needsLink && $user !== null && ! app(SpotifyControlLinkStatus::class)->isUsable($user)) {
                $validator->errors()->add('player_kind', 'This player needs a linked Spotify account. Link one, then come back to create your party.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please give your party a name.',
            'name.min' => 'The party name must be at least :min characters.',
            'name.max' => 'The party name may not be longer than :max characters.',
            'music_provider.required' => 'Please choose a music provider.',
            'music_provider.in' => 'That music provider is not available.',
            'player_kind.required' => 'Please choose a player.',
            'player_kind.in' => 'That player is not available.',
            'downvotes.boolean' => 'Downvotes must be either on or off.',
            'downvotes_per_hour.integer' => 'The downvote limit must be a whole number.',
            'downvotes_per_hour.min' => 'The downvote limit cannot be negative.',
            'downvotes_per_hour.max' => 'The downvote limit may not be more than :max per hour.',
            'selection_mode.enum' => 'The selection mode must be deterministic or weighted.',
            'allow_requests.boolean' => 'Accepting requests must be either on or off.',
            'max_requests.integer' => 'The request limit must be a whole number.',
            'max_requests.min' => 'The request limit must be at least :min.',
            'max_requests.max' => 'The request limit may not be more than :max.',
            'hold_requests.boolean' => 'Holding requests for approval must be either on or off.',
        ];
    }
}
