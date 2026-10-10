<?php

namespace App\Http\Requests;

use App\Domain\Queue\PlayHistoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPlayHistoryRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:100'],
            'artist' => ['nullable', 'string', 'max:100'],
            'album' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', Rule::enum(PlayHistoryType::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.enum' => 'Choose sent to Spotify, requested or fallback playlist.',
            'name.max' => 'Search terms may be at most 100 characters.',
            'artist.max' => 'Search terms may be at most 100 characters.',
            'album.max' => 'Search terms may be at most 100 characters.',
        ];
    }

    /**
     * @return array{name: string, artist: string, album: string, type: string}
     */
    public function filters(): array
    {
        return [
            'name' => trim($this->string('name')->toString()),
            'artist' => trim($this->string('artist')->toString()),
            'album' => trim($this->string('album')->toString()),
            'type' => $this->string('type')->toString() ?: PlayHistoryType::Sent->value,
        ];
    }
}
