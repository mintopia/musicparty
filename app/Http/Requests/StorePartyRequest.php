<?php

namespace App\Http\Requests;

use App\Domain\Party\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'music_provider' => 'required|string|in:'.implode(',', array_keys(config('musicparty.music_providers', []))),
            'player_kind' => 'required|string|in:'.implode(',', array_keys(config('musicparty.players', []))),
        ];
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
        ];
    }
}
